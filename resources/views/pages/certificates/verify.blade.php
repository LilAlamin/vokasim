<x-portfolio-public-layout :title="'Verifikasi Sertifikat'">
    <article class="flex flex-col gap-7">
        <header class="flex flex-col items-start gap-4">
            <flux:heading size="xl" level="1">Verifikasi Sertifikat</flux:heading>
            <flux:badge :color="$verification['is_valid'] ? 'emerald' : 'red'" size="lg">{{ $verification['status'] }}</flux:badge>
            <p class="max-w-prose text-zinc-600 dark:text-zinc-300">{{ $verification['is_valid'] ? 'Data sertifikat ini telah diperiksa oleh pembimbing atau administrator VokaSim dan masih berlaku.' : 'Sertifikat ini tidak berlaku saat ini. Jangan menggunakannya sebagai bukti kompetensi yang masih aktif.' }}</p>
        </header>
        <dl class="grid gap-5 border-y border-zinc-200 py-7 sm:grid-cols-2 dark:border-zinc-800">
            <div class="sm:col-span-2"><dt class="text-sm text-zinc-600 dark:text-zinc-400">Nama sertifikat</dt><dd class="mt-1 break-words text-xl font-semibold">{{ $verification['title'] }}</dd></div>
            <div><dt class="text-sm text-zinc-600 dark:text-zinc-400">Pemegang sertifikat</dt><dd class="mt-1 break-words font-medium">{{ $verification['holder_name'] }}</dd></div>
            <div><dt class="text-sm text-zinc-600 dark:text-zinc-400">Lembaga penerbit</dt><dd class="mt-1 break-words font-medium">{{ $verification['issuer'] }}</dd></div>
            <div><dt class="text-sm text-zinc-600 dark:text-zinc-400">Tanggal terbit</dt><dd class="mt-1">{{ $verification['issued_on'] }}</dd></div>
            <div><dt class="text-sm text-zinc-600 dark:text-zinc-400">Berlaku sampai</dt><dd class="mt-1">{{ $verification['expires_on'] ?? 'Tidak dicantumkan oleh penerbit' }}</dd></div>
            <div><dt class="text-sm text-zinc-600 dark:text-zinc-400">Diverifikasi pada</dt><dd class="mt-1">{{ $verification['verified_on'] }}</dd></div>
        </dl>
        <flux:text>Verifikasi ini mencerminkan pemeriksaan dokumen oleh institusi melalui VokaSim. Untuk konfirmasi penerbitan, hubungi lembaga yang tercantum pada sertifikat.</flux:text>
    </article>
</x-portfolio-public-layout>
