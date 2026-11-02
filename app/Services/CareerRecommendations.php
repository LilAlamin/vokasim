<?php

namespace App\Services;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\PortfolioProject;
use App\Models\PracticumAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * @phpstan-type CareerPath array{key: string, title: string, description: string, required_skills: list<string>}
 * @phpstan-type CompetencyEvidence array{source: string, id: int, title: string, detail: string}
 * @phpstan-type CareerMatch array{key: string, title: string, description: string, required_skills: list<string>, matched_skills: list<string>, missing_skills: list<string>, match_percentage: int}
 */
class CareerRecommendations
{
    /** @return list<CareerPath> */
    public function paths(): array
    {
        return [
            ['key' => 'web-developer', 'title' => 'Junior Web Developer', 'description' => 'Membangun aplikasi web, mengelola data, dan berkolaborasi melalui kendali versi.', 'required_skills' => ['php', 'laravel', 'sql', 'git']],
            ['key' => 'network-technician', 'title' => 'Teknisi Jaringan', 'description' => 'Memasang jaringan, mengonfigurasi perangkat, dan menelusuri gangguan koneksi.', 'required_skills' => ['jaringan komputer', 'tcp/ip', 'routing', 'troubleshooting']],
            ['key' => 'cnc-operator', 'title' => 'Operator CNC Junior', 'description' => 'Menyiapkan proses pemesinan dan memeriksa dimensi komponen dengan aman.', 'required_skills' => ['cnc', 'gambar teknik', 'metrologi', 'k3']],
            ['key' => 'automation-technician', 'title' => 'Teknisi Otomasi', 'description' => 'Merawat sistem kendali, sensor, dan rangkaian elektronik pada proses industri.', 'required_skills' => ['plc', 'elektronika', 'sensor', 'k3']],
            ['key' => 'welding-technician', 'title' => 'Teknisi Pengelasan', 'description' => 'Mengerjakan sambungan logam sesuai gambar dan memeriksa mutu hasil pekerjaan.', 'required_skills' => ['pengelasan', 'gambar teknik', 'inspeksi kualitas', 'k3']],
            ['key' => 'commis-chef', 'title' => 'Commis Chef', 'description' => 'Menyiapkan bahan dan hidangan dengan praktik kebersihan dan keselamatan pangan.', 'required_skills' => ['pengolahan makanan', 'higiene pangan', 'mise en place', 'k3']],
        ];
    }

    /** @return array{recommendations: list<CareerMatch>, evidence: array<string, list<CompetencyEvidence>>, declared_skills: list<string>} */
    public function forStudent(User $student): array
    {
        abort_unless($student->isMahasiswa(), 403);
        $evidence = $this->evidenceFor($student);
        $recommendations = [];

        foreach ($this->paths() as $path) {
            $matched = array_values(array_filter($path['required_skills'], fn (string $skill): bool => isset($evidence[$skill])));
            $recommendations[] = [
                ...$path,
                'matched_skills' => $matched,
                'missing_skills' => array_values(array_diff($path['required_skills'], $matched)),
                'match_percentage' => (int) round(count($matched) / count($path['required_skills']) * 100),
            ];
        }

        usort($recommendations, fn (array $first, array $second): int => $second['match_percentage'] <=> $first['match_percentage'] ?: strcmp($first['title'], $second['title']));

        $declared = PortfolioProject::query()->where('student_id', $student->id)->get(['skills'])
            ->flatMap(fn (PortfolioProject $project): array => $project->skills)
            ->map(fn (string $skill): string => $this->normalize($skill))->unique()->sort()->values()->all();

        return ['recommendations' => $recommendations, 'evidence' => $evidence, 'declared_skills' => array_values($declared)];
    }

    /** @return array<string, list<CompetencyEvidence>> */
    private function evidenceFor(User $student): array
    {
        $evidence = [];
        $certificates = Certificate::query()->where('student_id', $student->id)
            ->where('status', CertificateStatus::Verified)->whereNotNull('verified_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()->toDateString()))
            ->orderBy('id')->get(['id', 'title', 'issuer', 'skills']);

        foreach ($certificates as $certificate) {
            foreach ($certificate->skills as $skill) {
                $evidence[$this->normalize($skill)][] = ['source' => 'certificate', 'id' => $certificate->id, 'title' => $certificate->title, 'detail' => 'Sertifikat terverifikasi · '.$certificate->issuer];
            }
        }

        $assessments = PracticumAssessment::query()
            ->whereHas('submission', fn (Builder $submissions) => $submissions->where('student_id', $student->id)->where('status', 'graded')->latestAttempts())
            ->with('submission.assignment.module')->orderBy('id')->get();

        foreach ($assessments as $assessment) {
            foreach ($assessment->scores as $criterion) {
                if ($criterion['max_score'] <= 0 || $criterion['score'] / $criterion['max_score'] < 0.7) {
                    continue;
                }

                $skill = $this->normalize($criterion['title']);
                $evidence[$skill][] = [
                    'source' => 'practicum',
                    'id' => $assessment->submission->assignment->practicum_module_id,
                    'title' => $assessment->submission->assignment->title,
                    'detail' => 'Rubrik '.$criterion['title'].' · '.number_format($criterion['score'], 1).' / '.number_format($criterion['max_score'], 1),
                ];
            }
        }

        ksort($evidence);

        return $evidence;
    }

    private function normalize(string $skill): string
    {
        return Str::lower(Str::squish($skill));
    }
}
