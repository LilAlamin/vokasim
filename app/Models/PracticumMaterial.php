<?php

namespace App\Models;

use Database\Factories\PracticumMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $practicum_module_id
 * @property int $uploaded_by
 * @property string $title
 * @property string $kind
 * @property string $file_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property-read PracticumModule $module
 */
#[Fillable(['practicum_module_id', 'uploaded_by', 'title', 'kind', 'file_path', 'original_name', 'mime_type', 'size'])]
#[Hidden(['file_path'])]
class PracticumMaterial extends Model
{
    /** @use HasFactory<PracticumMaterialFactory> */
    use HasFactory;

    /** @return BelongsTo<PracticumModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(PracticumModule::class, 'practicum_module_id');
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'k3' => 'Keselamatan K3', 'sop' => 'SOP laboratorium', default => 'Materi praktik',
        };
    }

    public function canPreview(): bool
    {
        return in_array($this->mime_type, ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'], true);
    }
}
