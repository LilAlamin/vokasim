<x-layouts::auth :title="__('Daftar Akun VokaSim')">
    <div class="flex flex-col gap-6" x-data="{ role: '{{ old('role', 'mahasiswa') }}' }">
        <x-auth-header 
            title="Daftar Akun VokaSim" 
            description="Pilih peran Anda dan lengkapi data untuk mulai menggunakan platform vokasi terpadu" 
        />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Hidden Role Input -->
            <input type="hidden" name="role" :value="role" />

            <!-- Role Selector Cards -->
            <div class="flex flex-col gap-2">
                <flux:label>Pilih Peran Anda</flux:label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <!-- Mahasiswa Card -->
                    <button 
                        type="button" 
                        @click="role = 'mahasiswa'" 
                        :class="role === 'mahasiswa' 
                            ? 'border-blue-600 bg-blue-50/60 dark:bg-blue-950/40 dark:border-blue-500 ring-2 ring-blue-500/20' 
                            : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700 bg-white dark:bg-zinc-900'"
                        class="flex flex-col items-center text-center p-3.5 rounded-xl border transition-all text-left cursor-pointer focus:outline-none"
                    >
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mb-2 bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300">
                            <flux:icon name="academic-cap" class="size-5" />
                        </div>
                        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">Mahasiswa</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Praktik, Logbook & AI</span>
                    </button>

                    <!-- Dosen Card -->
                    <button 
                        type="button" 
                        @click="role = 'dosen'" 
                        :class="role === 'dosen' 
                            ? 'border-emerald-600 bg-emerald-50/60 dark:bg-emerald-950/40 dark:border-emerald-500 ring-2 ring-emerald-500/20' 
                            : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700 bg-white dark:bg-zinc-900'"
                        class="flex flex-col items-center text-center p-3.5 rounded-xl border transition-all text-left cursor-pointer focus:outline-none"
                    >
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mb-2 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300">
                            <flux:icon name="user-group" class="size-5" />
                        </div>
                        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">Dosen</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Instruktur & Pembimbing</span>
                    </button>

                    <!-- Mentor Industri Card -->
                    <button 
                        type="button" 
                        @click="role = 'mentor_dudi'" 
                        :class="role === 'mentor_dudi' 
                            ? 'border-amber-600 bg-amber-50/60 dark:bg-amber-950/40 dark:border-amber-500 ring-2 ring-amber-500/20' 
                            : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700 bg-white dark:bg-zinc-900'"
                        class="flex flex-col items-center text-center p-3.5 rounded-xl border transition-all text-left cursor-pointer focus:outline-none"
                    >
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mb-2 bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300">
                            <flux:icon name="building-office-2" class="size-5" />
                        </div>
                        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">Mentor DUDI</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Penyelia Industri</span>
                    </button>
                </div>
                @error('role')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </div>

            <!-- Identitas Pribadi -->
            <div class="flex flex-col gap-4">
                <!-- Name -->
                <flux:input
                    name="name"
                    label="Nama Lengkap"
                    :value="old('name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Nama lengkap beserta gelar (jika ada)"
                />

                <!-- Email Address -->
                <flux:input
                    name="email"
                    label="Alamat Email"
                    :value="old('email')"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="email@kampus.ac.id atau email@perusahaan.com"
                />

                <!-- Phone Number -->
                <flux:input
                    name="phone"
                    label="Nomor WhatsApp / HP (Opsional)"
                    :value="old('phone')"
                    type="tel"
                    placeholder="Contoh: 081234567890"
                />
            </div>

            <!-- Dynamic Role-Specific Fields -->
            <div class="p-4 rounded-xl border border-zinc-200/80 bg-zinc-50/60 dark:bg-zinc-900/50 dark:border-zinc-800 flex flex-col gap-4">
                <!-- Role: Mahasiswa -->
                <div x-show="role === 'mahasiswa'" class="flex flex-col gap-4">
                    <flux:input
                        name="identifier_number"
                        label="Nomor Induk Mahasiswa (NIM)"
                        :value="old('identifier_number')"
                        type="text"
                        ::disabled="role !== 'mahasiswa'"
                        placeholder="Contoh: 210441100012"
                    />

                    <flux:input
                        name="study_program"
                        label="Program Studi / Kejuruan"
                        :value="old('study_program')"
                        type="text"
                        ::disabled="role !== 'mahasiswa'"
                        placeholder="Contoh: D4 Teknik Informatika, D3 Teknik Mesin"
                    />
                </div>

                <!-- Role: Dosen -->
                <div x-show="role === 'dosen'" class="flex flex-col gap-4" style="display: none;">
                    <flux:input
                        name="identifier_number"
                        label="NIP / NIDN"
                        :value="old('identifier_number')"
                        type="text"
                        ::disabled="role !== 'dosen'"
                        placeholder="Contoh: 198507202012121002"
                    />

                    <flux:input
                        name="study_program"
                        label="Departemen / Bidang Keahlian"
                        :value="old('study_program')"
                        type="text"
                        ::disabled="role !== 'dosen'"
                        placeholder="Contoh: Teknik Manufaktur & Otomasi"
                    />
                </div>

                <!-- Role: Mentor DUDI -->
                <div x-show="role === 'mentor_dudi'" class="flex flex-col gap-4" style="display: none;">
                    <flux:input
                        name="institution_or_company"
                        label="Nama Perusahaan / Industri Mitra (DUDI)"
                        :value="old('institution_or_company')"
                        type="text"
                        ::disabled="role !== 'mentor_dudi'"
                        placeholder="Contoh: PT Astra Honda Motor, PT Telkom Indonesia"
                    />

                    <flux:input
                        name="identifier_number"
                        label="ID Karyawan / Posisi Jabatan (Opsional)"
                        :value="old('identifier_number')"
                        type="text"
                        ::disabled="role !== 'mentor_dudi'"
                        placeholder="Contoh: Senior Maintenance Engineer"
                    />
                </div>
            </div>

            <!-- Password -->
            <div class="flex flex-col gap-4">
                <flux:input
                    name="password"
                    label="Password"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Minimal 8 karakter"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <!-- Confirm Password -->
                <flux:input
                    name="password_confirmation"
                    label="Konfirmasi Password"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Ulangi password di atas"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />
            </div>

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full text-base font-semibold py-2.5" data-test="register-user-button">
                    Daftar Akun VokaSim
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>Sudah memiliki akun?</span>
            <flux:link :href="route('login')" wire:navigate class="font-medium text-blue-600 dark:text-blue-400">Masuk di sini</flux:link>
        </div>
    </div>
</x-layouts::auth>
