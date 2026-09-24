<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['path', 'original_name', 'mime_type', 'size', 'uploaded_by'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * أنواع الملفات المقبولة: مستندات العمل وصورها فقط، لا ما يمكن تنفيذه أو
     * عرضه كصفحة (html, svg) من نطاق النظام.
     */
    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'zip'];

    public const MAX_KILOBYTES = 20 * 1024;

    /**
     * القرص الخاص (storage/app/private): الملفات لا تُقدَّم مباشرة أبداً،
     * بل عبر AttachmentController بعد فحص الصلاحية.
     */
    public const DISK = 'local';

    /**
     * يحفظ الملف بمسار يولّده الخادم وحده. الاسم الأصلي يُحفظ للعرض فقط ولا
     * يدخل في بناء أي مسار: اسم يرفعه المستخدم يصلح لاجتياز المسار.
     */
    public static function storeUpload(UploadedFile $file, Model $attachable, User $uploader): self
    {
        $extension = strtolower($file->guessExtension() ?? $file->extension() ?? 'bin');
        $path = $file->storeAs(
            'attachments/'.now()->format('Y/m'),
            Str::uuid()->toString().'.'.$extension,
            self::DISK,
        );

        return $attachable->attachments()->create([
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $uploader->id,
        ]);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * المشروع الذي ينتمي إليه المرفق، سواء رُفع على المشروع نفسه أو على مهمة
     * أو عقد أو أمر شراء فيه.
     */
    public function project(): Project
    {
        return $this->attachable instanceof Project ? $this->attachable : $this->attachable->project;
    }

    public function deleteWithFile(): void
    {
        Storage::disk(self::DISK)->delete($this->path);
        $this->delete();
    }

    public function humanSize(): string
    {
        $size = (int) $this->size;

        return match (true) {
            $size >= 1048576 => round($size / 1048576, 1).' م.ب',
            $size >= 1024 => round($size / 1024).' ك.ب',
            default => $size.' بايت',
        };
    }
}
