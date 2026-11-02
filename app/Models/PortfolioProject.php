<?php

namespace App\Models;

use App\Concerns\ScopesPortfolioAccess;
use Carbon\CarbonImmutable;
use Database\Factories\PortfolioProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $student_id
 * @property string $public_id
 * @property string $title
 * @property string $description
 * @property list<string> $skills
 * @property string|null $project_url
 * @property CarbonImmutable|null $completed_on
 * @property string|null $evidence_path
 * @property string|null $evidence_name
 * @property string|null $evidence_mime
 * @property int|null $evidence_size
 * @property bool $is_published
 * @property-read User $student
 */
#[Fillable(['student_id', 'title', 'description', 'skills', 'project_url', 'completed_on', 'evidence_path', 'evidence_name', 'evidence_mime', 'evidence_size', 'is_published'])]
#[Hidden(['evidence_path'])]
class PortfolioProject extends Model
{
    /** @use HasFactory<PortfolioProjectFactory> */
    use HasFactory, HasUuids, ScopesPortfolioAccess;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['skills' => 'array', 'completed_on' => 'immutable_date', 'is_published' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
