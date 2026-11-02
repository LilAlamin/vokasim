document.addEventListener('alpine:init', () => {
    Alpine.data('interviewRecorder', (options) => {
        let stream, audioRecorder, videoRecorder, peer, remoteAudio, clock, voiceTimeout;
        let audioChunks = [], videoChunks = [], startedAt = 0, destroyed = false;
        const clockOffset = options.serverNow - Date.now();
        return {
            remaining: Math.max(0, Math.ceil((options.deadline - options.serverNow) / 1000)),
            recording: false, busy: false, submitting: false, speaking: false,
            error: '', status: '', audioPreview: '', expiredAttempted: false,
            get timeLabel() {
                return `${Math.floor(this.remaining / 60)}:${String(this.remaining % 60).padStart(2, '0')}`;
            },
            init() {
                clock = setInterval(async () => {
                    this.remaining = Math.max(0, Math.ceil((options.deadline - Date.now() - clockOffset) / 1000));
                    if (this.remaining === 0 && !this.expiredAttempted && !this.busy && !this.submitting) {
                        this.expiredAttempted = true;
                        if (this.recording) await this.stopRecording();
                        if (!this.error) await this.submit();
                    }
                }, 500);
            },
            async startRecording() {
                if (this.busy || this.recording) return;
                this.error = '';
                if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                    this.error = 'Rekaman memerlukan HTTPS dan browser yang mendukung mikrofon. Gunakan kolom teks bila rekaman tidak tersedia.';
                    return;
                }
                this.busy = true;
                this.closeVoice();
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        audio: { echoCancellation: true },
                        video: options.mode === 'video' ? { width: { ideal: 640 }, height: { ideal: 360 }, frameRate: { ideal: 15, max: 24 } } : false,
                    });
                    if (destroyed || this.remaining === 0) { this.stopTracks(); return; }
                    audioChunks = []; videoChunks = [];
                    const audioType = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/webm'].find(type => MediaRecorder.isTypeSupported(type));
                    audioRecorder = new MediaRecorder(new MediaStream(stream.getAudioTracks()), { ...(audioType ? { mimeType: audioType } : {}), audioBitsPerSecond: 32000 });
                    audioRecorder.ondataavailable = event => { if (event.data.size) audioChunks.push(event.data); };
                    if (options.mode === 'video') {
                        const videoType = ['video/webm;codecs=vp8,opus', 'video/mp4', 'video/webm'].find(type => MediaRecorder.isTypeSupported(type));
                        videoRecorder = new MediaRecorder(stream, { ...(videoType ? { mimeType: videoType } : {}), videoBitsPerSecond: 240000, audioBitsPerSecond: 32000 });
                        videoRecorder.ondataavailable = event => { if (event.data.size) videoChunks.push(event.data); };
                        if (this.$refs.preview) this.$refs.preview.srcObject = stream;
                        videoRecorder.start(1000);
                    }
                    audioRecorder.start(1000);
                    startedAt = Date.now();
                    this.recording = true;
                    this.status = 'Mikrofon aktif. Tekan Selesai rekam setelah menjawab.';
                } catch (error) {
                    this.stopTracks();
                    this.error = error.name === 'NotAllowedError' ? 'Akses mikrofon/kamera ditolak. Izinkan melalui pengaturan browser atau ketik jawaban.' : 'Perangkat belum dapat merekam. Periksa mikrofon/kamera atau ketik jawaban.';
                } finally { this.busy = false; }
            },
            async stopRecording() {
                if (!this.recording || this.busy) return;
                this.busy = true; this.error = '';
                try {
                    const duration = Math.max(0.1, Math.min(options.maxSeconds, (Date.now() - startedAt) / 1000));
                    await Promise.all([audioRecorder, videoRecorder].filter(Boolean).map(recorder => new Promise(resolve => {
                        if (recorder.state === 'inactive') return resolve();
                        recorder.onstop = resolve; recorder.stop();
                    })));
                    this.stopTracks(); this.recording = false;
                    if (destroyed) return;
                    const audioBlob = new Blob(audioChunks, { type: audioRecorder.mimeType });
                    if (this.audioPreview) URL.revokeObjectURL(this.audioPreview);
                    this.audioPreview = URL.createObjectURL(audioBlob);
                    if (audioBlob.size === 0) throw new Error('empty');
                    const files = [{ name: 'audio', blob: audioBlob }];
                    if (videoRecorder) files.push({ name: 'video', blob: new Blob(videoChunks, { type: videoRecorder.mimeType }) });
                    this.$wire.$set('recorded_seconds', duration, false);
                    for (const file of files) {
                        if (file.blob.size > 12 * 1024 * 1024) throw new Error('size');
                        this.status = `Mengunggah ${file.name === 'audio' ? 'audio' : 'video'}…`;
                        const extension = file.blob.type.includes('mp4') ? 'mp4' : 'webm';
                        await new Promise((resolve, reject) => this.$wire.$upload(
                            file.name,
                            new File([file.blob], `answer.${extension}`, { type: file.blob.type }),
                            resolve,
                            () => reject(new Error('upload')),
                            event => { this.status = `Mengunggah ${file.name}: ${event.detail.progress}%`; },
                            () => reject(new Error('cancelled')),
                        ));
                    }
                    this.status = 'Membuat transkrip audio…';
                    const transcribed = await this.$wire.transcribe(options.questionId);
                    this.status = transcribed ? 'Rekaman diunggah. Periksa transkrip, lalu kirim jawaban.' : 'Rekaman diunggah. Transkripsi belum berhasil; ketik jawaban atau coba transkripsikan ulang.';
                } catch (error) {
                    this.error = error.message === 'size' ? 'Rekaman melebihi 12 MB. Simpan salinan audio dan ketik jawaban untuk melanjutkan.' : 'Rekaman belum selesai diunggah atau ditranskripsikan. Simpan salinan audio, periksa pesan validasi, lalu ketik jawaban jika perlu.';
                } finally { this.recording = false; this.busy = false; this.stopTracks(); }
            },
            async submit() {
                if (this.busy || this.recording || this.submitting || destroyed) return;
                this.submitting = true; this.error = ''; this.closeVoice();
                try {
                    await this.$wire.sendAnswer(options.questionId);
                } catch {
                    this.error = 'Jawaban belum terkirim. Periksa koneksi lalu kirim kembali.';
                } finally { this.submitting = false; }
            },
            async speak() {
                if (this.speaking || this.busy || this.recording) return;
                this.error = ''; this.speaking = true;
                try {
                    peer = new RTCPeerConnection();
                    peer.addTransceiver('audio', { direction: 'recvonly' });
                    remoteAudio = new Audio(); remoteAudio.autoplay = true;
                    peer.ontrack = event => { remoteAudio.srcObject = event.streams[0]; remoteAudio.play().catch(() => { this.error = 'Browser memblokir suara. Bacalah pertanyaan atau coba putar kembali.'; this.closeVoice(); }); };
                    const channel = peer.createDataChannel('interview-voice');
                    channel.onopen = () => channel.send(JSON.stringify({ type: 'response.create' }));
                    channel.onmessage = event => {
                        const message = JSON.parse(event.data);
                        if (message.type === 'output_audio_buffer.stopped') this.closeVoice();
                        if (message.type === 'error') { this.error = 'Suara AI terhenti. Pertanyaan tetap dapat dibaca di layar.'; this.closeVoice(); }
                    };
                    const offer = await peer.createOffer();
                    await peer.setLocalDescription(offer);
                    const response = await fetch(options.voiceUrl, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                        body: JSON.stringify({ sdp: offer.sdp }),
                    });
                    if (!response.ok) throw new Error('voice');
                    if (destroyed || !peer) return;
                    await peer.setRemoteDescription({ type: 'answer', sdp: await response.text() });
                    voiceTimeout = setTimeout(() => this.closeVoice(), 45000);
                } catch {
                    this.error = 'Suara AI belum tersedia. Pertanyaan tetap dapat dibaca di layar.';
                    this.closeVoice();
                }
            },
            closeVoice() {
                clearTimeout(voiceTimeout); peer?.close(); peer = null;
                if (remoteAudio) { remoteAudio.pause(); remoteAudio.srcObject = null; }
                this.speaking = false;
            },
            stopTracks() { stream?.getTracks().forEach(track => track.stop()); },
            destroy() {
                destroyed = true; clearInterval(clock); this.closeVoice();
                [audioRecorder, videoRecorder].filter(Boolean).forEach(recorder => { if (recorder.state !== 'inactive') recorder.stop(); });
                this.stopTracks();
                if (this.audioPreview) URL.revokeObjectURL(this.audioPreview);
            },
        };
    });
});
