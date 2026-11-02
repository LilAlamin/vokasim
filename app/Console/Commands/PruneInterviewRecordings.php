<?php

namespace App\Console\Commands;

use App\Models\InterviewQuestion;
use App\Models\InterviewSession;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('interviews:prune-recordings {--dry-run : Count recordings without deleting them}')]
#[Description('Remove private interview recordings after the configured retention period')]
class PruneInterviewRecordings extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) config('interviews.media_retention_days', 90)));
        $questions = InterviewQuestion::query()->where('answered_at', '<=', $cutoff)
            ->where(fn ($query) => $query->whereNotNull('audio_path')->orWhereNotNull('video_path'));
        if ($this->option('dry-run')) {
            $this->info($questions->count().' jawaban memiliki rekaman yang melewati masa simpan.');

            return self::SUCCESS;
        }
        $count = 0;
        foreach ($questions->lazyById() as $question) {
            $paths = array_values(array_filter([$question->audio_path, $question->video_path]));
            if (! Storage::disk('local')->delete($paths)) {
                $this->error('Rekaman jawaban '.$question->id.' belum dapat dihapus.');

                return self::FAILURE;
            }
            $question->update(['audio_path' => null, 'video_path' => null]);
            $count++;
        }
        foreach (InterviewSession::query()->where('completed_at', '<=', $cutoff)->lazyById() as $session) {
            $session->update(['context_snapshot' => []]);
        }
        $this->info('Rekaman pada '.$count.' jawaban dihapus sesuai masa simpan.');

        return self::SUCCESS;
    }
}
