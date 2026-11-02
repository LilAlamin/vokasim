# Product Requirements Document (PRD): VokaSim Platform

**Nama Produk:** VokaSim (Vocational Simulation & Management Platform)  
**Versi Dokumen:** 1.0  
**Status:** Draft Awal  
**Target Rilis:** Q1 - Q2  

---

## 1. Ringkasan Eksekutif (Executive Summary)
**VokaSim Platform** adalah ekosistem digital terpadu yang dirancang khusus untuk institusi pendidikan vokasi (Politeknik, Sekolah Vokasi, SMK/Balai Latihan Kerja). Platform ini menjembatani kegiatan akademik berbasis praktik di kampus, program magang di Dunia Usaha dan Dunia Industri (DUDI), hingga kesiapan karier lulusan. 

Dilengkapi dengan fitur unggulan **Simulasi Wawancara HRD Berbasis AI (AI Mock Interview)**, VokaSim tidak hanya mencatat capaian kompetensi teknis (*hard skill*), tetapi juga melatih kesiapan mental dan komunikasi profesional (*soft skill*) mahasiswa sebelum terjun ke pasar kerja.

---

## 2. Tujuan Produk (Product Objectives)
1. **Digitalisasi Praktikum & Magang:** Mengintegrasikan pemantauan kegiatan laboratorium/bengkel di kampus dan kegiatan magang di industri dalam satu platform *real-time*.
2. **Transparansi Asesmen Kompetensi:** Memudahkan dosen dan mentor industri (DUDI) dalam memberikan penilaian berbasis rubrik (*hard skill* & *soft skill*) secara objektif.
3. **Akselerasi Kesiapan Kerja (Job Readiness):** Membekali mahasiswa dengan portofolio digital terverifikasi dan kemampuan menghadapi rekrutmen melalui simulasi wawancara berbasis AI.
4. **Pengambilan Keputusan Berbasis Data:** Menyediakan analitik eksekutif terkait serapan alumni (*tracer study*), efektivitas kemitraan MoU/MOA, dan keselarasan kurikulum dengan tren pasar kerja.

---

## 3. Target Pengguna (User Personas)
1. **Peserta Didik / Mahasiswa:** Pengguna utama yang menjalani praktikum, mengisi logbook magang, menyusun portofolio, dan berlatih wawancara kerja.
2. **Dosen / Instruktur Praktik:** Tenaga pendidik yang mengawasi praktikum di bengkel/lab, menilai rubrik kompetensi, dan membimbing mahasiswa magang.
3. **Penyelia / Mentor Industri (Mitra DUDI):** Perwakilan perusahaan mitra yang memvalidasi aktivitas harian dan mengevaluasi kinerja mahasiswa magang di lapangan.
4. **Manajemen / Administrator (Eksekutif):** Pimpinan institusi (Direktur, Dekan, Kaprodi, CDC) yang memantau metrik keterserapan lulusan dan mengelola kemitraan industri.

---

## 4. Kebutuhan Fungsional & Spesifikasi Fitur (Functional Requirements)

### 4.1. Modul Peserta Didik / Mahasiswa
| Sub-Modul / Layar | Deskripsi & Kebutuhan Fitur | Prioritas |
| :--- | :--- | :--- |
| **Dashboard Utama** | • Ringkasan persentase capaian kompetensi.<br>• Informasi IPK dan nilai rata-rata praktik.<br>• Widget jadwal sesi praktikum/magang terdekat.<br>• Bilah notifikasi status tugas & persetujuan logbook. | P0 (Tinggi) |
| **Layar Katalog & Modul Praktikum** | • Daftar modul keahlian dengan filter semester dan bidang keahlian.<br>• *Viewer* interaktif untuk panduan keselamatan (K3) & SOP laboratorium.<br>• Fitur unduh materi praktik dan pengumpulan (*submission*) tugas/laporan. | P0 (Tinggi) |
| **Layar E-Logbook & Magang** | • Formulir pengisian aktivitas harian magang (deskripsi kegiatan, durasi jam kerja, unggah foto bukti).<br>• Kalender presensi berbasis lokasi (*Geofencing* / GPS).<br>• Indikator pelacakan status persetujuan dari mentor industri & dosen. | P0 (Tinggi) |
| **Layar Portofolio & Sertifikasi Digital** | • Galeri pameran hasil karya/proyek (*project showcase*).<br>• Dompet penyimpanan sertifikat kompetensi digital dilengkapi QR Code verifikasi.<br>• Mesin rekomendasi jalur karier otomatis berbasis kompetensi yang telah dicapai. | P1 (Menengah) |
| **Layar Simulasi Wawancara HRD AI *(Fitur Baru)*** | • **Kustomisasi Sesi:** Pilihan posisi pekerjaan target (berdasarkan rekomendasi karier/prodi), tingkat kesulitan, dan tipe wawancara (HR Behavior, Teknis Vokasi, atau Kasuistik).<br>• **Mode Interaksi:** Simulasi interaktif dengan Avatar/Suara AI HRD (*Voice-to-Text* & *Text-to-Speech*) atau berbasis percakapan teks/video.<br>• **Integrasi Konteks:** AI HRD menghasilkan pertanyaan yang relevan dengan isi Portofolio, Sertifikasi, dan riwayat E-Logbook magang mahasiswa.<br>• **Instant AI Feedback:** Laporan evaluasi otomatis pasca-wawancara yang mencakup skor metode *STAR* (*Situation, Task, Action, Result*), analisis intonasi/kepercayaan diri, ketepatan jawaban teknis, dan saran perbaikan. | P0 (Tinggi) |

---

### 4.2. Modul Dosen / Instruktur Praktik
| Sub-Modul / Layar | Deskripsi & Kebutuhan Fitur | Prioritas |
| :--- | :--- | :--- |
| **Dashboard Kelas & Bengkel** | • Statistik kehadiran mahasiswa di laboratorium/bengkel.<br>• Daftar kelas praktik aktif yang diampu.<br>• Pengingat (*reminder*) jadwal ujian praktik. | P0 (Tinggi) |
| **Layar Asesmen & Penilaian Praktik** | • Formulir penilaian digital berbasis rubrik terstruktur (*hard skill* & *soft skill*).<br>• Antarmuka responsif (ramah tablet/mobile) untuk input skor secara *real-time* saat mengawasi mahasiswa di bengkel/lab. | P0 (Tinggi) |
| **Layar Verifikasi Logbook & Bimbingan** | • Panel peninjauan laporan harian/mingguan mahasiswa magang.<br>• Kolom umpan balik (*feedback*) atau catatan revisi.<br>• Tombol persetujuan cepat (*quick approve*) tunggal maupun massal (*bulk*). | P0 (Tinggi) |
| **Panel Pantauan Kesiapan Wawancara *(Integrasi AI)*** | • Melihat riwayat skor simulasi wawancara AI dari mahasiswa bimbingan.<br>• Memberikan catatan tambahan pada rekaman/transkrip latihan wawancara mahasiswa. | P2 (Rendah) |

---

### 4.3. Modul Penyelia / Mentor Industri (Mitra DUDI)
| Sub-Modul / Layar | Deskripsi & Kebutuhan Fitur | Prioritas |
| :--- | :--- | :--- |
| **Dashboard Pendampingan** | • Daftar mahasiswa magang yang ditempatkan di unit kerja terkait.<br>• Indikator visual kedisiplinan dan persentase capaian target kerja harian. | P0 (Tinggi) |
| **Layar Evaluasi Kinerja Industri** | • Matriks penilaian sikap kerja, etika profesional, dan penguasaan alat standar industri.<br>• Sistem pengisian berkala (evaluasi bulanan dan evaluasi akhir magang). | P0 (Tinggi) |
| **Layar Validasi Presensi & Aktivitas** | • Panel persetujuan jam kerja efektif harian.<br>• Rekapitulasi keikutsertaan mahasiswa dalam proyek lapangan.<br>• Validasi pengajuan izin atau sakit dari mahasiswa. | P0 (Tinggi) |

---

### 4.4. Modul Manajemen / Administrator (Executive)
| Sub-Modul / Layar | Deskripsi & Kebutuhan Fitur | Prioritas |
| :--- | :--- | :--- |
| **Executive Dashboard (Analitik Vokasi)** | • Grafik tingkat keterserapan alumni di industri.<br>• Statistik rasio dosen/instruktur terhadap mahasiswa praktik.<br>• Peta sebaran geografis kemitraan DUDI secara *real-time*. | P1 (Menengah) |
| **Layar Manajemen Kemitraan (MoU & DUDI)** | • Database terpusat profil perusahaan mitra.<br>• Sistem pelacakan masa berlaku dokumen kerja sama (MoU/MOA) beserta notifikasi pengingat kedaluwarsa.<br>• Pengaturan alokasi kuota magang per industri/perusahaan. | P1 (Menengah) |
| **Layar Tracer Study & Analisis Pasar Kerja** | • Panel pemantauan alumni (rata-rata waktu tunggu mendapatkan kerja, persentase keselarasan bidang, rata-rata gaji pertama).<br>• Pemetaan tren keahlian (*skill gap analysis*) yang paling banyak dicari pasar kerja.<br>• **Analitik Kesiapan Kerja (AI Interview):** Statistik rata-rata skor kesiapan wawancara mahasiswa per program studi sebelum lulus. | P1 (Menengah) |

---

## 5. Detail Alur Kerja Fitur Baru: Simulasi Wawancara HRD AI (User Flow)

1. **Persiapan (Setup):**
   * Mahasiswa membuka menu **Simulasi Wawancara AI**.
   * Sistem secara otomatis menarik data dari **Layar Portofolio & Sertifikasi** serta ringkasan **E-Logbook Magang** mahasiswa sebagai konteks dasar.
   * Mahasiswa memilih target posisi pekerjaan (misal: *Junior CNC Operator*, *Network Technician*, atau *Sous Chef*) dan memilih mode wawancara (Suara/Video atau Teks).
2. **Pelaksanaan (Execution):**
   * AI HRD memulai sesi dengan perkenalan dan mengajukan 5–10 pertanyaan bertahap:
     * *Tahap 1:* Pertanyaan pengenalan diri & motivasi.
     * *Tahap 2:* Pertanyaan perilaku (*Behavioral*) dan konfirmasi pengalaman magang (berdasarkan catatan E-Logbook).
     * *Tahap 3:* Pertanyaan skenario masalah teknis sesuai bidang studi vokasi.
   * Mahasiswa menjawab secara langsung melalui mikrofon/kamera (dengan batas waktu per jawaban agar terasa nyata).
3. **Evaluasi & Umpan Balik (Evaluation):**
   * Setelah sesi selesai, AI memproses transkrip dan audio mahasiswa.
   * Layar menampilkan **Skor Kesiapan Wawancara (0-100)** dengan rincian:
     * **Relevansi Jawaban & Metode STAR:** Apakah jawaban terstruktur dan menjawab inti pertanyaan?
     * **Komunikasi & Kepercayaan Diri:** Kecepatan bicara (*pacing*), penggunaan kata pengisi (*filler words* seperti "umm", "eh"), dan kejelasan artikulasi.
     * **Penguasaan Teknis:** Ketepatan istilah teknis vokasi yang digunakan.
   * Sistem memberikan contoh jawaban yang lebih optimal untuk setiap pertanyaan yang dijawab kurang tepat oleh mahasiswa.

---

## 6. Kebutuhan Non-Fungsional (Non-Functional Requirements)
1. **Performa & Latensi (Khusus Fitur AI):** Respons suara/teks dari AI HRD pada saat simulasi wawancara harus memiliki latensi rendah (di bawah 2–3 detik) agar percakapan terasa alami.
2. **Akurasi Lokasi (Geofencing):** Validasi GPS pada presensi E-Logbook harus memiliki toleransi radius yang dapat dikonfigurasi (misal: radius 50–100 meter dari titik koordinat perusahaan mitra DUDI) dan dilengkapi deteksi *Fake GPS*.
3. **Keamanan & Privasi Data:** 
   * Sertifikat digital harus terenkripsi dan memiliki URL verifikasi unik pada QR Code untuk mencegah pemalsuan.
   * Rekaman suara/video dari simulasi wawancara AI hanya dapat diakses oleh mahasiswa terkait dan dosen pembimbingnya.
4. **Kompatibilitas Perangkat (Responsiveness):** 
   * Antarmuka Mahasiswa (E-Logbook), Dosen (Asesmen Bengkel), dan Mentor DUDI (Validasi Presensi) wajib dioptimalkan untuk perangkat *mobile* (Smartphone/Tablet).
   * Antarmuka Executive Dashboard dioptimalkan untuk layar Desktop.

---

## 7. Metrik Keberhasilan (Success Metrics / KPI)
* **Tingkat Adopsi Logbook & Asesmen:** > 90% laporan magang dan penilaian praktikum dilakukan secara *paperless* melalui platform.
* **Efektivitas Fitur Wawancara AI:** Peningkatan skor rata-rata simulasi wawancara mahasiswa sebesar minimal 25% setelah melakukan 3 kali percobaan latihan.
* **Tingkat Respons Mitra DUDI:** Waktu rata-rata persetujuan (*approval*) logbook dan evaluasi oleh penyelia industri kurang dari 48 jam.
* **Dampak Keterserapan Lulusan:** Penurunan rata-rata waktu tunggu kerja alumni pada laporan *Tracer Study* di tahun berikutnya.