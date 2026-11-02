import './interviews.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('internshipGeolocation', () => ({
        busy: false,
        error: '',
        async record(type) {
            if (this.busy) return;
            this.error = '';
            if (!window.isSecureContext) {
                this.error = 'GPS memerlukan koneksi HTTPS. Buka aplikasi melalui alamat HTTPS lalu coba lagi.';
                return;
            }
            if (!navigator.geolocation) {
                this.error = 'Perangkat ini tidak mendukung lokasi GPS. Gunakan perangkat yang mendukung.';
                return;
            }
            this.busy = true;
            try {
                const position = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        maximumAge: 0,
                        timeout: 20000,
                    });
                });
                const age = Date.now() - position.timestamp;
                if (age > 30000 || age < -5000) {
                    this.error = 'Lokasi sudah kedaluwarsa. Coba ambil lokasi kembali.';
                    return;
                }
                await this.$wire.recordAttendance(type, {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                });
            } catch (error) {
                this.error = {
                    1: 'Akses lokasi ditolak. Izinkan lokasi di pengaturan browser, lalu coba lagi.',
                    2: 'Lokasi tidak tersedia. Aktifkan GPS dan pindah ke area terbuka.',
                    3: 'Pengambilan lokasi terlalu lama. Periksa GPS, lalu coba lagi.',
                }[error.code] || 'Presensi belum berhasil. Periksa koneksi dan pesan validasi, lalu coba lagi.';
            } finally {
                this.busy = false;
            }
        },
    }));
});
