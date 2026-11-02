@php
    $items = match (auth()->user()->role) {
        \App\Enums\UserRole::Mahasiswa => [
            ['dashboard', 'Dashboard', 'home', 'dashboard'],
            ['practicums.index', 'Modul & Praktikum', 'book-open', 'practicums.*'],
            ['internships.logbook', 'E-Logbook Magang', 'clipboard-document-check', 'internships.logbook'],
            ['internships.leaves', 'Izin & Sakit', 'calendar-days', 'internships.leaves'],
            ['industry.index', 'Rekap & Proyek Magang', 'briefcase', 'industry.*'],
            ['internships.evaluations', 'Evaluasi Magang', 'star', 'internships.evaluations'],
            ['portfolios.index', 'Portofolio', 'trophy', 'portfolios.*'],
            ['certificates.index', 'Sertifikat Kompetensi', 'check-badge', 'certificates.*'],
            ['careers.index', 'Rekomendasi Karier', 'map', 'careers.*'],
            ['interviews.index', 'Latihan Wawancara AI', 'sparkles', 'interviews.*'],
            ['tracer.survey', 'Survei Alumni', 'chart-bar', 'tracer.survey'],
        ],
        \App\Enums\UserRole::Dosen => [
            ['dashboard', 'Dashboard', 'home', 'dashboard'],
            ['practicums.index', 'Kelas & Asesmen Praktik', 'building-library', 'practicums.*'],
            ['internships.reviews', 'Verifikasi Logbook', 'clipboard-document-check', 'internships.reviews'],
            ['industry.index', 'Rekap Magang Bimbingan', 'briefcase', 'industry.*'],
            ['internships.evaluations', 'Evaluasi Industri', 'star', 'internships.evaluations'],
            ['portfolios.index', 'Portofolio Mahasiswa', 'trophy', 'portfolios.*'],
            ['certificates.index', 'Verifikasi Sertifikat', 'check-badge', 'certificates.*'],
            ['interviews.index', 'Pantauan Wawancara AI', 'sparkles', 'interviews.*'],
        ],
        \App\Enums\UserRole::MentorDudi => [
            ['dashboard', 'Dashboard', 'home', 'dashboard'],
            ['industry.index', 'Mahasiswa & Proyek', 'user-group', 'industry.*'],
            ['internships.reviews', 'Validasi Logbook & GPS', 'map-pin', 'internships.reviews'],
            ['internships.leaves', 'Persetujuan Izin & Sakit', 'calendar-days', 'internships.leaves'],
            ['internships.evaluations', 'Evaluasi Kinerja DUDI', 'star', 'internships.evaluations'],
        ],
        \App\Enums\UserRole::Admin => [
            ['dashboard', 'Executive Dashboard', 'home', 'dashboard'],
            ['admin.users', 'Pengguna & Akademik', 'users', 'admin.*'],
            ['practicums.index', 'Kelas & Praktikum', 'building-library', 'practicums.*'],
            ['internships.placements', 'Penempatan Magang', 'map-pin', 'internships.placements'],
            ['industry.index', 'Rekap Magang Industri', 'briefcase', 'industry.*'],
            ['partners.index', 'Kemitraan DUDI & MoU', 'building-office-2', 'partners.*'],
            ['portfolios.index', 'Portofolio Mahasiswa', 'trophy', 'portfolios.*'],
            ['certificates.index', 'Verifikasi Sertifikat', 'check-badge', 'certificates.*'],
            ['tracer.index', 'Tracer Study & Skill Gap', 'chart-bar', 'tracer.index'],
        ],
    };
@endphp

<flux:sidebar.nav>
    <flux:sidebar.group :heading="auth()->user()->role->label()" class="grid">
        @foreach ($items as [$routeName, $label, $icon, $active])
            @if (\Illuminate\Support\Facades\Route::has($routeName))
                <flux:sidebar.item wire:key="nav-{{ $routeName }}" :icon="$icon" :href="route($routeName)" :current="request()->routeIs($active)" wire:navigate>{{ $label }}</flux:sidebar.item>
            @endif
        @endforeach
    </flux:sidebar.group>
    @if (\Illuminate\Support\Facades\Route::has('notifications.index'))
        <flux:sidebar.item icon="bell" :href="route('notifications.index')" :current="request()->routeIs('notifications.*')" wire:navigate>Notifikasi</flux:sidebar.item>
    @endif
</flux:sidebar.nav>
