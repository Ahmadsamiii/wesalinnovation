<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrEmployeesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_lists_every_account_that_is_not_a_client(): void
    {
        $hr = User::factory()->role('hr')->create(['name' => 'منى الموارد']);
        $designer = User::factory()->role('team_member')->create(['name' => 'ريم الشهري', 'department' => 'التصميم', 'job_title' => 'مصممة', 'joined_at' => '2024-03-01']);
        User::factory()->role('pm')->deactivated()->create(['name' => 'سعد الموقوف']);
        User::factory()->role('medical')->pendingInvitation()->create(['name' => 'هند المعلّقة']);
        User::factory()->role('client')->create(['name' => 'شركة العميل']);
        User::factory()->create(['name' => 'حساب بلا دور']);

        $this->actingAs($hr)->get(route('hr.employees'))
            ->assertOk()
            ->assertSee('الموظفون')
            ->assertSeeInOrder(['منى الموارد', 'مدير الموارد البشرية'])
            ->assertSee($hr->employeeNumber())
            ->assertSeeInOrder(['ريم الشهري', 'عضو الفريق', $designer->employeeNumber(), 'التصميم', 'مصممة', '2024'])
            ->assertSee('سعد الموقوف')
            ->assertSee('موقوف')
            ->assertSee('هند المعلّقة')
            ->assertSee('بانتظار قبول الدعوة')
            ->assertSee(route('hr.employees.edit', $designer), false)
            ->assertDontSee('شركة العميل')
            ->assertDontSee('حساب بلا دور');
    }

    public function test_hr_searches_by_name_and_filters_by_role(): void
    {
        $hr = User::factory()->role('hr')->create(['name' => 'منى الموارد']);
        User::factory()->role('team_member')->create(['name' => 'ريم الشهري']);
        User::factory()->role('team_member')->create(['name' => 'خالد العمري']);
        User::factory()->role('pm')->create(['name' => 'ريما المديرة']);

        $this->actingAs($hr)->get(route('hr.employees', ['q' => 'ريم']))
            ->assertOk()
            ->assertSee('ريم الشهري')
            ->assertSee('ريما المديرة')
            ->assertDontSee('خالد العمري');

        $this->actingAs($hr)->get(route('hr.employees', ['role' => 'team_member']))
            ->assertOk()
            ->assertSee('ريم الشهري')
            ->assertSee('خالد العمري')
            ->assertDontSee('ريما المديرة')
            ->assertDontSee($hr->employeeNumber().'</td>', false);

        $this->actingAs($hr)->get(route('hr.employees', ['q' => 'ريم', 'role' => 'pm']))
            ->assertSee('ريما المديرة')
            ->assertDontSee('ريم الشهري');

        $this->actingAs($hr)->get(route('hr.employees', ['role' => 'client']))->assertSessionHasErrors('role');
    }

    public function test_the_page_says_when_nothing_matches(): void
    {
        $hr = User::factory()->role('hr')->create();

        $this->actingAs($hr)->get(route('hr.employees', ['q' => 'لا أحد بهذا الاسم']))
            ->assertOk()
            ->assertSee('لا موظفين مطابقين.');
    }

    public function test_only_hr_and_sysadmin_open_the_employees_page(): void
    {
        $this->get(route('hr.employees'))->assertRedirect(route('login'));

        foreach (['executive', 'pm', 'finance', 'medical', 'team_member', 'client', 'crm'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('hr.employees'))->assertForbidden();
        }

        foreach (['hr', 'sysadmin'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('hr.employees'))->assertOk();
        }
    }

    public function test_sysadmin_reads_the_list_but_can_neither_open_nor_send_the_edit_form(): void
    {
        $sysadmin = User::factory()->role('sysadmin')->create();
        $employee = User::factory()->role('team_member')->create(['department' => 'التصميم']);

        $this->actingAs($sysadmin)->get(route('hr.employees'))
            ->assertOk()
            ->assertSee($employee->name)
            ->assertDontSee(route('hr.employees.edit', $employee), false);

        $this->actingAs($sysadmin)->get(route('hr.employees.edit', $employee))->assertForbidden();
        $this->actingAs($sysadmin)->put(route('hr.employees.update', $employee), ['department' => 'الهندسة'])->assertForbidden();
        $this->assertSame('التصميم', $employee->fresh()->department);
    }

    public function test_hr_edits_only_department_job_title_and_joined_date(): void
    {
        $hr = User::factory()->role('hr')->create();
        $employee = User::factory()->role('team_member')->create([
            'name' => 'ريم الشهري',
            'email' => 'reem@example.test',
            'phone' => '0501234567',
            'department' => 'التصميم',
            'job_title' => 'مصممة',
            'joined_at' => '2024-03-01',
        ]);

        $this->actingAs($hr)->get(route('hr.employees.edit', $employee))
            ->assertOk()
            ->assertSee('الأدوار وحالة الحساب تُدار من صفحة المستخدمين في المنصة')
            ->assertSee('reem@example.test')
            ->assertSee('0501234567')
            ->assertSee('عضو الفريق')
            ->assertSee('name="department"', false)
            ->assertSee('name="job_title"', false)
            ->assertSee('name="joined_at"', false)
            ->assertDontSee('name="name"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('name="phone"', false)
            ->assertDontSee('name="role"', false);

        $this->actingAs($hr)->put(route('hr.employees.update', $employee), [
            'department' => 'الهندسة',
            'job_title' => 'مصممة أولى',
            'joined_at' => '2024-05-15',
            'name' => 'اسم آخر',
            'email' => 'other@example.test',
            'phone' => '0555555555',
            'role' => 'executive',
            'deactivated_at' => now()->toDateTimeString(),
        ])->assertRedirect(route('hr.employees'))->assertSessionHasNoErrors();

        $employee->refresh();
        $this->assertSame('الهندسة', $employee->department);
        $this->assertSame('مصممة أولى', $employee->job_title);
        $this->assertSame('2024-05-15', $employee->joined_at->toDateString());

        // ما تملكه المنصة لا يتغير ولو أُرسل.
        $this->assertSame('ريم الشهري', $employee->name);
        $this->assertSame('reem@example.test', $employee->email);
        $this->assertSame('0501234567', $employee->phone);
        $this->assertSame('team_member', $employee->roleName());
        $this->assertFalse($employee->isDeactivated());
    }

    public function test_the_change_is_written_to_the_audit_log_and_shown_to_the_sysadmin(): void
    {
        $hr = User::factory()->role('hr')->create();
        $employee = User::factory()->role('team_member')->create(['department' => 'التصميم', 'job_title' => 'مصممة', 'joined_at' => '2024-03-01']);

        $this->actingAs($hr)->put(route('hr.employees.update', $employee), [
            'department' => 'الهندسة',
            'job_title' => 'مصممة',
            'joined_at' => '2024-05-15',
        ]);

        $log = AuditLog::where('action', AuditAction::EmployeeUpdated->value)->sole();
        $this->assertSame($hr->id, $log->user_id);
        $this->assertSame($employee->id, $log->subject_id);
        $this->assertSame(['department', 'joined_at'], $log->properties['fields']);
        $this->assertSame(['from' => 'التصميم', 'to' => 'الهندسة'], $log->properties['changes']['department']);
        $this->assertSame(['from' => '2024-03-01', 'to' => '2024-05-15'], $log->properties['changes']['joined_at']);

        $sysadmin = User::factory()->role('sysadmin')->create();
        $this->actingAs($sysadmin)->get(route('audit.index'))
            ->assertOk()
            ->assertSee('تعديل بيانات وظيفية لموظف')
            ->assertSee('القسم: من «التصميم» إلى «الهندسة»');
        $this->actingAs($sysadmin)->get(route('audit.index', ['group' => 'hr']))
            ->assertOk()
            ->assertSee('الموارد البشرية')
            ->assertSee('تعديل بيانات وظيفية لموظف');
    }

    public function test_saving_without_a_change_leaves_no_audit_entry_and_empty_values_clear_the_field(): void
    {
        $hr = User::factory()->role('hr')->create();
        $employee = User::factory()->role('pm')->create(['department' => 'الهندسة', 'job_title' => 'مدير', 'joined_at' => '2024-03-01']);

        $this->actingAs($hr)->put(route('hr.employees.update', $employee), ['department' => 'الهندسة', 'job_title' => 'مدير', 'joined_at' => '2024-03-01'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, AuditLog::where('action', AuditAction::EmployeeUpdated->value)->count());

        $this->actingAs($hr)->put(route('hr.employees.update', $employee), ['department' => '', 'job_title' => 'مدير', 'joined_at' => ''])
            ->assertSessionHasNoErrors();

        $employee->refresh();
        $this->assertNull($employee->department);
        $this->assertNull($employee->joined_at);
        $this->assertSame(['from' => 'الهندسة', 'to' => null], AuditLog::sole()->properties['changes']['department']);
    }

    public function test_the_three_fields_are_validated(): void
    {
        $hr = User::factory()->role('hr')->create();
        $employee = User::factory()->role('pm')->create(['department' => 'الهندسة']);

        $this->actingAs($hr)->put(route('hr.employees.update', $employee), [
            'department' => str_repeat('ق', 101),
            'job_title' => str_repeat('م', 101),
            'joined_at' => 'ليس تاريخاً',
        ])->assertSessionHasErrors(['department', 'job_title', 'joined_at']);

        $this->assertSame('الهندسة', $employee->fresh()->department);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_hr_can_edit_hr_accounts_including_their_own(): void
    {
        $hr = User::factory()->role('hr')->create(['job_title' => 'مسؤولة']);

        $this->actingAs($hr)->get(route('hr.employees.edit', $hr))->assertOk();
        $this->actingAs($hr)->put(route('hr.employees.update', $hr), ['job_title' => 'مديرة الموارد البشرية'])->assertSessionHasNoErrors();

        $this->assertSame('مديرة الموارد البشرية', $hr->fresh()->job_title);
    }

    public function test_clients_and_accounts_without_a_role_have_no_edit_page(): void
    {
        $hr = User::factory()->role('hr')->create();

        foreach ([User::factory()->role('client')->create(), User::factory()->create()] as $target) {
            $this->actingAs($hr)->get(route('hr.employees.edit', $target))->assertNotFound();
            $this->actingAs($hr)->put(route('hr.employees.update', $target), ['department' => 'أي قسم'])->assertNotFound();
            $this->assertNull($target->fresh()->department);
        }
    }

    public function test_other_roles_cannot_send_the_edit_form(): void
    {
        $employee = User::factory()->role('team_member')->create(['department' => 'التصميم']);

        foreach (['executive', 'pm', 'finance', 'medical', 'team_member', 'client', 'crm'] as $role) {
            $actor = User::factory()->role($role)->create();

            $this->actingAs($actor)->get(route('hr.employees.edit', $employee))->assertForbidden();
            $this->actingAs($actor)->put(route('hr.employees.update', $employee), ['department' => 'الهندسة'])->assertForbidden();
        }

        $this->assertSame('التصميم', $employee->fresh()->department);
        $this->assertSame(0, AuditLog::count());
    }
}
