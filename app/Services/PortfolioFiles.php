<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioFiles
{
    /** @return array{path: string, name: string, mime: string, size: int} */
    public function store(UploadedFile $file, string $directory, string $field): array
    {
        $contents = $file->get();

        if ($contents === false) {
            throw ValidationException::withMessages([$field => 'Berkas tidak dapat dibaca. Silakan unggah ulang.']);
        }

        $path = $directory.'/'.Str::uuid().'.encrypted';

        if (! Storage::disk('local')->put($path, Crypt::encryptString($contents))) {
            throw ValidationException::withMessages([$field => 'Berkas gagal disimpan. Silakan coba lagi.']);
        }

        return [
            'path' => $path,
            'name' => Str::limit($file->getClientOriginalName(), 240, ''),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => strlen($contents),
        ];
    }

    public function download(string $path, string $name, string $mime): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);
        $encrypted = Storage::disk('local')->get($path);
        abort_unless(is_string($encrypted), 404);

        try {
            $contents = Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            abort(409, 'Berkas tidak dapat diverifikasi. Hubungi administrator.');
        }

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $name.'.'.$this->extension($mime), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
