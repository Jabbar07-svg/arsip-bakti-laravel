import { useEffect, useRef, useState } from 'react';
import type { Html5Qrcode } from 'html5-qrcode';

type QrScannerProps = {
    lookupUrl: string;
};

type CameraOption = {
    id: string;
    label: string;
};

export function QrScanner({ lookupUrl }: QrScannerProps) {
    const readerRef = useRef<HTMLDivElement>(null);
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const cameraActiveRef = useRef(false);
    const [cameras, setCameras] = useState<CameraOption[]>([]);
    const [cameraId, setCameraId] = useState('');
    const [running, setRunning] = useState(false);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('Tekan “Aktifkan kamera” untuk meminta izin kamera.');
    const [messageState, setMessageState] = useState<'info' | 'success' | 'error'>('info');

    useEffect(() => {
        return () => {
            const scanner = scannerRef.current;
            if (scanner && cameraActiveRef.current) {
                void scanner.stop().then(() => scanner.clear()).catch((error: unknown) => {
                    console.error('Kamera gagal dihentikan saat halaman ditutup.', error);
                });
            }
        };
    }, []);

    const showError = (error: unknown) => {
        const name = error instanceof DOMException ? error.name : '';
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            return 'Kamera memerlukan HTTPS atau localhost. Jika dibuka dari HP melalui alamat jaringan, aktifkan HTTPS.';
        }
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
            return 'Izin kamera ditolak. Izinkan kamera untuk situs ini di pengaturan browser lalu coba lagi.';
        }
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
            return 'Kamera tidak ditemukan. Gunakan input kode manual.';
        }
        if (name === 'NotReadableError' || name === 'TrackStartError') {
            return 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi tersebut lalu coba lagi.';
        }
        return 'Kamera gagal dibuka. Periksa izin kamera atau gunakan input kode manual.';
    };

    const startCamera = async (preferredCameraId = cameraId) => {
        setBusy(true);
        setMessage('Meminta izin kamera…');
        setMessageState('info');
        try {
            const { Html5Qrcode, Html5QrcodeSupportedFormats } = await import('html5-qrcode');
            const devices = await Html5Qrcode.getCameras();
            if (devices.length === 0) {
                throw new DOMException('No camera available', 'NotFoundError');
            }
            const options = devices.map((device, index) => ({
                id: device.id,
                label: device.label || `Kamera ${index + 1}`,
            }));
            setCameras(options);
            const rearCamera = devices.find((device) => /back|rear|environment/i.test(device.label));
            const selectedCamera = preferredCameraId || rearCamera?.id || devices[0].id;
            setCameraId(selectedCamera);

            const reader = readerRef.current;
            if (!reader) {
                throw new Error('Area pratinjau kamera tidak tersedia.');
            }
            const scanner = new Html5Qrcode(reader.id, {
                verbose: false,
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.CODE_93,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E,
                    Html5QrcodeSupportedFormats.ITF,
                ],
            });
            scannerRef.current = scanner;
            await scanner.start(
                selectedCamera,
                {
                    fps: 10,
                    qrbox: (viewfinderWidth, viewfinderHeight) => ({
                        width: Math.floor(viewfinderWidth * 0.96),
                        height: Math.floor(viewfinderHeight * 0.96),
                    }),
                },
                async (decodedText) => {
                    setMessage('Kode terbaca. Membuka arsip…');
                    setMessageState('success');
                    try {
                        await scanner.stop();
                        scanner.clear();
                        cameraActiveRef.current = false;
                        setRunning(false);
                        const lookup = new URL(lookupUrl, window.location.href);
                        lookup.searchParams.set('code', decodedText.trim());
                        window.location.assign(lookup);
                    } catch (error) {
                        console.error('Kamera gagal dihentikan setelah kode terbaca.', error);
                        setMessage('Kode terbaca, tetapi kamera gagal dihentikan. Silakan cari menggunakan kode manual.');
                        setMessageState('error');
                    }
                },
                () => undefined,
            );
            cameraActiveRef.current = true;
            setRunning(true);
            setMessage('Arahkan kamera ke QR atau barcode arsip.');
            setMessageState('info');
        } catch (error) {
            console.error('Pemindai QR/barcode gagal dijalankan.', error);
            setMessage(showError(error));
            setMessageState('error');
        } finally {
            setBusy(false);
        }
    };

    const stopCamera = async () => {
        const scanner = scannerRef.current;
        if (!scanner) {
            return;
        }
        setBusy(true);
        try {
            await scanner.stop();
            scanner.clear();
            cameraActiveRef.current = false;
            setRunning(false);
            setMessage('Kamera dihentikan. Aktifkan kembali atau gunakan input kode manual.');
            setMessageState('info');
        } catch (error) {
            console.error('Kamera gagal dihentikan.', error);
            setMessage('Kamera gagal dihentikan. Muat ulang halaman untuk mencoba kembali.');
            setMessageState('error');
        } finally {
            setBusy(false);
        }
    };

    const changeCamera = async (nextCameraId: string) => {
        setCameraId(nextCameraId);
        if (running) {
            await stopCamera();
            window.setTimeout(() => void startCamera(nextCameraId), 0);
        }
    };

    return (
        <main className="page scanner-page">
            <div className="page-heading-row">
                <div>
                    <div className="eyebrow">Identifikasi arsip</div>
                    <h1>Scan QR / Barcode</h1>
                    <p className="page-description">Gunakan kamera perangkat atau masukkan kode arsip secara manual. Detail yang dibuka tetap mengikuti hak akses akun.</p>
                </div>
                <span className={`scanner-status${running ? ' is-active' : ''}`} role="status">{running ? 'Kamera aktif' : 'Kamera nonaktif'}</span>
            </div>

            <div className="scanner-layout">
                <section className="panel scanner-camera" aria-labelledby="scanner-camera-title">
                    <div className="scanner-panel-heading">
                        <div><h2 id="scanner-camera-title">Pindai menggunakan kamera</h2><p>Pastikan label berada dalam area pemindaian dan pencahayaan cukup.</p></div>
                    </div>
                    <div className="scanner-controls">
                        <label htmlFor="scanner-camera-select">Pilih kamera
                            <select id="scanner-camera-select" value={cameraId} disabled={!cameras.length || busy} onChange={(event) => void changeCamera(event.target.value)}>
                                {!cameras.length && <option value="">Pilih kamera setelah mulai</option>}
                                {cameras.map((camera) => <option key={camera.id} value={camera.id}>{camera.label}</option>)}
                            </select>
                        </label>
                        {!running
                            ? <button className="button button-primary" type="button" onClick={() => void startCamera()} disabled={busy}>{busy ? 'Menghubungkan…' : 'Aktifkan kamera'}</button>
                            : <button className="button" type="button" onClick={() => void stopCamera()} disabled={busy}>{busy ? 'Menghentikan…' : 'Berhenti'}</button>}
                    </div>
                    <div id="qr-reader" ref={readerRef} className="scanner-reader" aria-label="Pratinjau kamera untuk scan QR dan barcode" />
                    <p className={`scanner-message is-${messageState}`} role="status" aria-live="polite">{message}</p>
                </section>

                <section className="panel scanner-manual" aria-labelledby="scanner-manual-title">
                    <div className="scanner-panel-heading">
                        <div><h2 id="scanner-manual-title">Cari dengan kode</h2><p>Gunakan QR ID, barcode, nomor berkas, atau kode box.</p></div>
                    </div>
                    <form method="get" action={lookupUrl}>
                        <label htmlFor="archive-code">Kode arsip</label>
                        <input id="archive-code" name="code" maxLength={180} placeholder="Contoh: ARSIP-2026-09-000001" autoComplete="off" required />
                        <button className="button button-primary" type="submit">Cari arsip</button>
                    </form>
                    <div className="scanner-guidance">
                        <strong>Alur pemindaian</strong>
                        <ol><li>Aktifkan kamera dan izinkan akses.</li><li>Arahkan kamera ke label arsip.</li><li>Halaman detail dibuka sesuai hak akses.</li></ol>
                    </div>
                    <p className="scanner-note">Pemindaian kamera memerlukan HTTPS atau localhost. Jika akses kamera tidak tersedia, pencarian manual tetap dapat digunakan.</p>
                </section>
            </div>
        </main>
    );
}
