@props(['title'])

<!DOCTYPE html>
<html lang="id">
    <head>@include('partials.head', ['title' => $title])</head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <a href="#portfolio-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:p-3 focus:text-zinc-900">Lewati ke konten</a>
        <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-4xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2 rounded-md font-semibold focus-visible:outline-2 focus-visible:outline-offset-4"><x-app-logo-icon class="size-7" />VokaSim</a>
                <span class="text-sm text-zinc-600 dark:text-zinc-300">Portofolio & kompetensi</span>
            </div>
        </header>
        <main id="portfolio-content" class="mx-auto max-w-4xl px-5 py-10 sm:px-8 sm:py-14">{{ $slot }}</main>
        @fluxScripts
    </body>
</html>
