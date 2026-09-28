<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ربط حسابات مساحة العمل بحسابات المنصة العامة (الهوية الموحدة).
 *
 * يعمل على قاعدتين: المحلية (users هنا) والمنصة (اتصال platform). ثلاث عمليات:
 *
 *  - plan(): يقرأ الحسابين ويبني خطة بلا أي تعديل. كل حساب محلي إما يُربط بحساب
 *    المنصة ذي البريد نفسه، أو يُنشأ له حساب هناك بكلمة مروره المحلية نفسها،
 *    أو يُتجاوز (دعوة لم تُقبل، بلا دور)، أو يقع في تعارض يقرره صاحب القرار.
 *  - apply(): ينفّذ الخطة على القاعدتين ويكتب أثر كل ربط في account_links (في
 *    المنصة) لتُتراجَع الدفعة. إعادة التنفيذ لا تكرر شيئاً، وتُكمل ما انقطع.
 *  - undo(): يعيد ما غيّرته دفعة: أدوار الحسابات المربوطة، ويحذف حسابات أُنشئت
 *    لم يستعملها أحد (ويوقف ما استُعمل ويسحب دوره).
 *
 * لا يربط بالبريد وحده حساباً يحمل تعارضاً (مدير نظام في المنصة بدور مختلف هنا،
 * أو موقوف هناك)، ولا يمنح مدير نظام في المنصة إلا بموافقة صريحة على الترقية.
 * الحساب الموجود في الطرفين تبقى كلمة مرور المنصة فيه.
 */
final class AccountLinker
{
    /** دور مدير النظام هنا هو admin في المنصة، ويُشتق ولا يُخزَّن مرتين. */
    private const SYSADMIN = 'sysadmin';

    /**
     * @return array{id: string, items: list<array<string, mixed>>, summary: array<string, int>, blockers: list<string>}
     */
    public function plan(bool $allowAdminPromotion = false): array
    {
        $platform = DB::connection('platform');
        $takenPhones = $platform->table('users')->whereNotNull('phone')->pluck('phone')->flip()->all();
        $linkedWs = $platform->table('account_links')->whereNull('reverted_at')->pluck('platform_user_id', 'workspace_user_id')->all();
        $linkedPl = array_flip($linkedWs);

        $items = [];
        foreach (User::query()->orderBy('id')->get() as $local) {
            $item = $this->itemFor($local, $platform, $allowAdminPromotion, $takenPhones, $linkedWs, $linkedPl);
            if (($item['create']['phone'] ?? null) !== null) {
                $takenPhones[$item['create']['phone']] = true;
            }
            $items[] = $item;
        }

        $blockers = $this->blockers($items);
        $summary = array_count_values(array_column($items, 'action'));
        $summary += [
            'link' => 0, 'create' => 0, 'skip' => 0, 'conflict' => 0, 'done' => 0,
            'total' => count($items),
            'promotes_admin' => count(array_filter($items, fn (array $i): bool => $i['flags']['promotes_admin'] ?? false)),
            'phone_dropped' => count(array_filter($items, fn (array $i): bool => $i['flags']['phone_dropped'] ?? false)),
        ];

        // معرّف الخطة بصمة محتواها: التنفيذ لا يجري إلا على خطة طابقت ما رُوجع.
        $id = hash('sha256', json_encode([$items, $allowAdminPromotion], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return ['id' => $id, 'items' => $items, 'summary' => $summary, 'blockers' => $blockers];
    }

    /**
     * @param  array<string, mixed>  $takenPhones
     * @param  array<int|string, mixed>  $linkedWs
     * @param  array<int|string, mixed>  $linkedPl
     * @return array<string, mixed>
     */
    private function itemFor(User $local, ConnectionInterface $platform, bool $allowAdmin, array $takenPhones, array $linkedWs, array $linkedPl): array
    {
        $role = $local->roleName();
        $state = match (true) {
            $local->isDeactivated() => 'deactivated',
            $local->invitation_accepted_at === null => 'pending',
            default => 'active',
        };

        $item = [
            'ws_id' => $local->id, 'email' => Str::lower($local->email), 'name' => $local->name,
            'role' => $role, 'state' => $state, 'action' => 'skip', 'reason' => '',
            'platform_id' => null, 'changes' => [], 'create' => null, 'flags' => [],
        ];

        if ($local->platform_user_id !== null || isset($linkedWs[$local->id])) {
            return [...$item, 'action' => 'done', 'reason' => 'already_linked', 'platform_id' => $local->platform_user_id ?? $linkedWs[$local->id]];
        }
        if ($role === null) {
            return [...$item, 'reason' => 'no_role'];
        }
        if ($state === 'pending') {
            return [...$item, 'reason' => 'pending_invitation'];
        }

        $wantsAdmin = $role === self::SYSADMIN;
        $existing = $platform->table('users')->whereRaw('LOWER(email) = ?', [$item['email']])->first();

        if ($existing !== null) {
            return $this->linkItem($item, $existing, $wantsAdmin, $allowAdmin, $linkedPl);
        }

        $phone = $this->platformPhone($local->phone, $takenPhones);
        $item['flags'] = ['phone_dropped' => $phone['note'] !== null];
        $item['create'] = ['phone' => $phone['value'], 'phone_note' => $phone['note']];
        $item['changes'] = ['role' => $wantsAdmin ? 'admin' : 'user', 'org_role' => $role, 'status' => $state === 'deactivated' ? 'suspended' : 'active'];

        if ($wantsAdmin) {
            $item['flags']['promotes_admin'] = true;
            if (! $allowAdmin) {
                return [...$item, 'action' => 'conflict', 'reason' => 'needs_admin_promotion'];
            }
        }

        return [...$item, 'action' => 'create', 'reason' => 'no_platform_account'];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int|string, mixed>  $linkedPl
     * @return array<string, mixed>
     */
    private function linkItem(array $item, object $existing, bool $wantsAdmin, bool $allowAdmin, array $linkedPl): array
    {
        $item['platform_id'] = (int) $existing->id;

        if (isset($linkedPl[$existing->id])) {
            return [...$item, 'action' => 'conflict', 'reason' => 'platform_account_linked_to_another'];
        }
        if ($existing->role === 'admin' && ! $wantsAdmin) {
            return [...$item, 'action' => 'conflict', 'reason' => 'platform_admin_with_other_local_role'];
        }
        if ($existing->status === 'suspended' && $item['state'] === 'active') {
            return [...$item, 'action' => 'conflict', 'reason' => 'platform_account_suspended'];
        }
        if (($existing->org_role ?? null) !== null && $existing->org_role !== $item['role']) {
            return [...$item, 'action' => 'conflict', 'reason' => 'platform_org_role_differs'];
        }

        // موقوف هنا: يُربط للتاريخ فقط بلا دور، فلا يُمنح دخولاً لم يكن له.
        if ($item['state'] === 'deactivated') {
            return [...$item, 'action' => 'link', 'reason' => 'matched_email_history_only'];
        }

        $changes = ['org_role' => $item['role']];
        if ($wantsAdmin && $existing->role !== 'admin') {
            $changes['role'] = 'admin';
            $item['flags']['promotes_admin'] = true;
            if (! $allowAdmin) {
                return [...$item, 'changes' => $changes, 'action' => 'conflict', 'reason' => 'needs_admin_promotion'];
            }
        }

        return [...$item, 'changes' => $changes, 'action' => 'link', 'reason' => 'matched_email'];
    }

    /**
     * الجوال بصيغة المنصة (05XXXXXXXX) أو فارغ مع سبب. الجوال الفريد شرط في المنصة.
     *
     * @param  array<string, mixed>  $taken
     * @return array{value: ?string, note: ?string}
     */
    private function platformPhone(?string $phone, array $taken): array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return ['value' => null, 'note' => null];
        }

        $digits = preg_replace('/^(00966|966)/', '0', $digits);
        if (preg_match('/^5\d{8}$/', (string) $digits) === 1) {
            $digits = '0'.$digits;
        }

        if (preg_match('/^05\d{8}$/', (string) $digits) !== 1) {
            return ['value' => null, 'note' => 'phone_invalid'];
        }

        return isset($taken[$digits]) ? ['value' => null, 'note' => 'phone_taken'] : ['value' => $digits, 'note' => null];
    }

    /**
     * ما يمنع التنفيذ أياً كان الباقي: ألا يبقى مدير نظام مربوط بحساب مدير في المنصة.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    private function blockers(array $items): array
    {
        $adminsAfter = array_filter($items, fn (array $i): bool => $i['role'] === self::SYSADMIN
            && $i['state'] === 'active'
            && (in_array($i['action'], ['link', 'create'], true) || $i['action'] === 'done'));

        return $adminsAfter === [] ? ['no_linked_sysadmin'] : [];
    }

    /**
     * ينفّذ الخطة، ويعيد معرّف الدفعة. التنفيذ المكرر لا يكرر شيئاً.
     *
     * @param  array{items: list<array<string, mixed>>}  $plan
     */
    public function apply(array $plan): string
    {
        $platform = DB::connection('platform');
        $batch = Str::lower(Str::random(12));

        foreach ($plan['items'] as $item) {
            if (! in_array($item['action'], ['link', 'create'], true)) {
                continue;
            }

            $done = $platform->table('account_links')->where('workspace_user_id', $item['ws_id'])->whereNull('reverted_at')->first();
            if ($done !== null) {
                $this->linkLocal((int) $item['ws_id'], (int) $done->platform_user_id);

                continue;
            }

            $platformId = $platform->transaction(fn (): int => $this->applyItem($platform, $item, $batch));
            $this->linkLocal((int) $item['ws_id'], $platformId);
        }

        return $batch;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function applyItem(ConnectionInterface $platform, array $item, string $batch): int
    {
        $prev = ['role' => null, 'org_role' => null, 'status' => null];

        if ($item['action'] === 'create') {
            $local = User::query()->findOrFail($item['ws_id']);
            $platformId = (int) $platform->table('users')->insertGetId([
                'name' => $local->name,
                'email' => Str::lower($local->email),
                'phone' => $item['create']['phone'],
                'pref' => 'simple',
                'pass_hash' => $local->getRawOriginal('password'),
                'role' => $item['changes']['role'],
                'org_role' => $item['changes']['org_role'],
                'status' => $item['changes']['status'],
                'must_change_pw' => 0,
                'tokens' => 30,
                'tokens_at' => now()->format('Y-m-d H:i:s'),
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);
        } else {
            $platformId = (int) $item['platform_id'];
            $row = $platform->table('users')->where('id', $platformId)->first();
            $prev = ['role' => $row->role, 'org_role' => $row->org_role ?? null, 'status' => $row->status];
            if ($item['changes'] !== []) {
                $platform->table('users')->where('id', $platformId)->update($item['changes']);
            }
        }

        $platform->table('account_links')->insert([
            'batch' => $batch,
            'workspace_user_id' => $item['ws_id'],
            'platform_user_id' => $platformId,
            'action' => $item['action'] === 'create' ? 'created' : 'linked',
            'prev_role' => $prev['role'],
            'prev_org_role' => $prev['org_role'],
            'prev_status' => $prev['status'],
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return $platformId;
    }

    private function linkLocal(int $workspaceUserId, int $platformUserId): void
    {
        User::query()->whereKey($workspaceUserId)->whereNull('platform_user_id')
            ->toBase()->update(['platform_user_id' => $platformUserId]);
    }

    /**
     * يتراجع عن دفعة: الحسابات المربوطة تعود لأدوارها السابقة، والمُنشأة تُحذف إن لم
     * يستعملها أحد، وإلا تُوقف ويُسحب دورها. يعيد أعداد ما جرى.
     *
     * @return array{restored: int, deleted: int, suspended: int}
     */
    public function undo(string $batch): array
    {
        $platform = DB::connection('platform');
        $result = ['restored' => 0, 'deleted' => 0, 'suspended' => 0];

        foreach ($platform->table('account_links')->where('batch', $batch)->whereNull('reverted_at')->get() as $link) {
            $platform->transaction(function () use ($platform, $link, &$result): void {
                if ($link->action === 'linked') {
                    $platform->table('users')->where('id', $link->platform_user_id)
                        ->update(['role' => $link->prev_role, 'org_role' => $link->prev_org_role, 'status' => $link->prev_status]);
                    $result['restored']++;
                } else {
                    $user = $platform->table('users')->where('id', $link->platform_user_id)->first();
                    $used = $user !== null && ($user->last_login !== null || (int) ($user->questions ?? 0) > 0);
                    if ($user !== null && ! $used) {
                        $platform->table('auth_sessions')->where('user_id', $link->platform_user_id)->delete();
                        $platform->table('users')->where('id', $link->platform_user_id)->delete();
                        $result['deleted']++;
                    } elseif ($user !== null) {
                        $platform->table('users')->where('id', $link->platform_user_id)->update(['org_role' => null, 'status' => 'suspended']);
                        $result['suspended']++;
                    }
                }
                $platform->table('account_links')->where('id', $link->id)->update(['reverted_at' => now()->format('Y-m-d H:i:s')]);
            });

            User::query()->whereKey($link->workspace_user_id)->toBase()->update(['platform_user_id' => null]);
        }

        return $result;
    }
}
