import './bootstrap';
import '../css/dashboard.css';
import '../css/archives.css';
import '../css/scanner.css';
import '../css/login.css';
import '../css/theme.css';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { ArchiveIndex } from './components/ArchiveIndex';
import { ResponsiveSidebarControls } from './components/ResponsiveSidebarControls';
import { DashboardApp } from './components/Dashboard';
import { QrScanner } from './components/QrScanner';

const themeStorageKey = 'arsip-bakti-theme';
const themeButtons = document.querySelectorAll('[data-theme-toggle]');
const getSavedTheme = () => {
    try {
        const theme = localStorage.getItem(themeStorageKey);
        return theme === 'light' || theme === 'dark' ? theme : null;
    } catch {
        return null;
    }
};
const applyTheme = (theme, persist = false) => {
    document.documentElement.dataset.theme = theme;
    themeButtons.forEach(button => {
        button.textContent = theme === 'dark' ? 'Gunakan tema terang' : 'Gunakan tema gelap';
        button.setAttribute('aria-pressed', String(theme === 'dark'));
    });
    if (persist) {
        try {
            localStorage.setItem(themeStorageKey, theme);
        } catch (error) {
            console.error('Preferensi tema tidak dapat disimpan.', error);
        }
    }
};
const colorScheme = window.matchMedia('(prefers-color-scheme: dark)');
themeButtons.forEach(button => {
    button.addEventListener('click', () => {
        applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark', true);
    });
});
colorScheme.addEventListener('change', event => {
    if (getSavedTheme() === null) {
        applyTheme(event.matches ? 'dark' : 'light');
    }
});
applyTheme(getSavedTheme() || (colorScheme.matches ? 'dark' : 'light'));

const sidebarControls = document.querySelector('[data-sidebar-controls]');
if (sidebarControls) {
    createRoot(sidebarControls).render(createElement(ResponsiveSidebarControls));
}

const dashboardHost = document.querySelector('#dashboard-app');
if (dashboardHost) {
    try {
        const propsElement = document.querySelector('#dashboard-props');
        if (!propsElement?.textContent) {
            throw new Error('Data dashboard tidak tersedia.');
        }
        const data = JSON.parse(propsElement.textContent);
        createRoot(dashboardHost).render(createElement(DashboardApp, { data }));
    } catch (error) {
        console.error('Dashboard gagal dimuat.', error);
        dashboardHost.innerHTML = '<main class="page dashboard-empty" role="alert"><strong>Dashboard belum dapat dimuat.</strong><span>Muat ulang halaman untuk mencoba kembali.</span><button class="button" type="button" data-dashboard-retry>Muat ulang</button></main>';
        dashboardHost.querySelector('[data-dashboard-retry]')?.addEventListener('click', () => window.location.reload());
    }
}

const scannerHost = document.querySelector('#qr-scanner-app');
if (scannerHost) {
    createRoot(scannerHost).render(createElement(QrScanner, {
        lookupUrl: scannerHost.getAttribute('data-lookup-url') || '',
    }));
}

const archiveHost = document.querySelector('#archives-app');
if (archiveHost) {
    try {
        const propsElement = document.querySelector('#archives-props');
        if (!propsElement?.textContent) {
            throw new Error('Data arsip tidak tersedia.');
        }
        const data = JSON.parse(propsElement.textContent);
        createRoot(archiveHost).render(createElement(ArchiveIndex, { data }));
    } catch (error) {
        console.error('Daftar arsip gagal dimuat.', error);
        archiveHost.innerHTML = '<main class="page archive-index" role="alert"><strong>Daftar arsip belum dapat dimuat.</strong><span>Muat ulang halaman untuk mencoba kembali.</span><button class="button" type="button" data-archives-retry>Muat ulang</button></main>';
        archiveHost.querySelector('[data-archives-retry]')?.addEventListener('click', () => window.location.reload());
    }
}

document.querySelector('[data-dashboard-app]')?.removeAttribute('aria-live');

document.querySelectorAll('[data-profile-form]').forEach(form => {
    const input = form.querySelector('[data-profile-image-input]');
    const preview = form.querySelector('[data-profile-preview]');
    const fallback = form.querySelector('[data-avatar-fallback]');
    const fileName = form.querySelector('[data-profile-file-name]');
    let previewUrl;

    input?.addEventListener('change', () => {
        const file = input.files?.[0];
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = undefined;
        }
        if (!file) {
            if (preview) {
                preview.hidden = true;
                preview.removeAttribute('src');
            }
            if (fallback) {
                fallback.hidden = false;
            }
            if (fileName) {
                fileName.textContent = 'Belum ada foto baru dipilih.';
            }
            return;
        }
        if (file.type.startsWith('image/') && preview) {
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.hidden = false;
            if (fallback) {
                fallback.hidden = true;
            }
        }
        if (fileName) {
            fileName.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
        }
    });
});

document.querySelectorAll('[data-file-preview-input]').forEach(input => {
    const form = input.closest('form');
    const preview = form?.querySelector('[data-file-preview]');
    const name = preview?.querySelector('[data-file-name]');
    const size = preview?.querySelector('[data-file-size]');
    const clear = preview?.querySelector('[data-file-clear]');

    const updatePreview = () => {
        const files = Array.from(input.files || []);
        if (files.length === 0 || !preview) {
            if (preview) {
                preview.hidden = true;
            }
            return;
        }
        if (name) {
            name.textContent = files.map(file => file.name).join(', ');
        }
        if (size) {
            const totalSize = files.reduce((total, file) => total + file.size, 0);
            const sizeLabel = `${(totalSize / 1024 / 1024).toFixed(2)} MB`;
            size.textContent = files.length > 1
                ? `${files.length} file · ${sizeLabel} total`
                : sizeLabel;
        }
        preview.hidden = false;
    };

    input.addEventListener('change', updatePreview);
    clear?.addEventListener('click', () => {
        input.value = '';
        updatePreview();
        input.focus();
    });
});

const submitButtons = new WeakMap();
const submitForms = document.querySelectorAll('[data-upload-form], [data-submit-form]');
submitForms.forEach(form => {
    const button = form.querySelector('[data-submit-button]');
    if (button) {
        submitButtons.set(button, button.innerHTML);
    }

    form.addEventListener('submit', event => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        const button = form.querySelector('[data-submit-button]');
        if (!button) {
            return;
        }
        form.dataset.submitting = 'true';
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        const label = button.querySelector('span');
        if (label) {
            label.textContent = 'Mengirim…';
        } else {
            button.textContent = 'Memproses…';
        }
    });
});

window.addEventListener('pageshow', () => {
    submitForms.forEach(form => {
        delete form.dataset.submitting;
        const button = form.querySelector('[data-submit-button]');
        if (!button) {
            return;
        }

        button.disabled = false;
        button.removeAttribute('aria-busy');
        const originalContent = submitButtons.get(button);
        if (originalContent !== undefined) {
            button.innerHTML = originalContent;
        }
    });
});

document.querySelectorAll('.profile-menu').forEach(menu => {
    document.addEventListener('click', event => {
        if (!menu.contains(event.target)) {
            menu.open = false;
        }
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        }
    });
});

const notificationFeed = document.body.dataset.notificationFeed;
if (notificationFeed) {
    const status = document.querySelector('[data-notification-status]');
    const toastRegion = document.querySelector('[data-notification-toasts]');
    let lastSeenId = 0;
    let polling = false;

    const showNotification = item => {
        const toast = document.createElement('article');
        toast.className = 'notification-toast';
        const title = document.createElement('strong');
        title.textContent = item.title || 'Arsip BAKTI';
        const body = document.createElement('p');
        body.textContent = item.body || '';
        toast.append(title, body);
        if (item.url && item.url !== './') {
            const target = new URL(item.url, window.location.origin);
            if (target.origin === window.location.origin) {
                const link = document.createElement('a');
                link.href = target.href;
                link.textContent = 'Buka notifikasi';
                toast.append(link);
            }
        }
        toastRegion.append(toast);
        window.setTimeout(() => toast.remove(), 9000);
    };

    const pollNotifications = async (baseline = false) => {
        if (polling) {
            return;
        }
        polling = true;
        try {
            const endpoint = new URL(notificationFeed, window.location.href);
            if (baseline) {
                endpoint.searchParams.set('baseline', '1');
            } else {
                endpoint.searchParams.set('after', String(lastSeenId));
            }
            const response = await fetch(endpoint, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) {
                throw new Error(`Notification feed returned HTTP ${response.status}.`);
            }
            const result = await response.json();
            if (result.ok !== true || !Array.isArray(result.items)) {
                throw new Error('Notification feed returned an invalid response.');
            }
            lastSeenId = Math.max(lastSeenId, Number(result.latest) || 0);
            result.items.forEach(showNotification);
            if (status) {
                status.textContent = '';
            }
        } catch (error) {
            if (status) {
                status.textContent = 'Pemberitahuan langsung sementara tidak tersambung.';
            }
            console.error('Tidak dapat memeriksa notifikasi terbaru.', error);
        } finally {
            polling = false;
        }
    };

    pollNotifications(true);
    window.setInterval(() => pollNotifications(), 15000);
}

const qrTargets = document.querySelectorAll('[data-qr-code]');
if (qrTargets.length > 0) {
    import('qrcode').then(({ default: QRCode }) => {
        qrTargets.forEach(target => {
            const canvas = document.createElement('canvas');
            target.replaceChildren(canvas);
            QRCode.toCanvas(canvas, target.dataset.qrCode, {
                width: Number(target.dataset.qrSize || 220),
                margin: 2,
                errorCorrectionLevel: 'H',
                color: { dark: '#000000', light: '#ffffff' },
            }, error => {
                if (error) {
                    target.replaceChildren();
                    const message = target.parentElement?.querySelector('[data-qr-error]');
                    if (message) {
                        message.hidden = false;
                    }
                    console.error('QR code gagal dibuat.', error);
                }
            });
        });

        document.querySelectorAll('[data-download-qr]').forEach(button => {
            button.addEventListener('click', () => {
                const target = document.getElementById(button.dataset.downloadQr);
                const canvas = target?.querySelector('canvas');
                if (!canvas) {
                    console.error('QR code belum tersedia untuk diunduh.');
                    return;
                }
                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = button.dataset.filename || 'arsip-qr.png';
                link.click();
            });
        });
    }).catch(error => {
        console.error('Pustaka QR code gagal dimuat.', error);
        document.querySelectorAll('[data-qr-error]').forEach(message => {
            message.hidden = false;
        });
    });
}

const barcodeTargets = document.querySelectorAll('[data-barcode]');
if (barcodeTargets.length > 0) {
    import('jsbarcode').then(({ default: JsBarcode }) => {
        barcodeTargets.forEach(target => {
            try {
                JsBarcode(target, target.dataset.barcode, {
                    format: 'CODE128',
                    width: 2,
                    height: 58,
                    displayValue: true,
                    fontSize: 12,
                    margin: 4,
                    lineColor: '#172b3a',
                });
            } catch (error) {
                const message = target.parentElement?.querySelector('[data-barcode-error]');
                if (message) {
                    message.hidden = false;
                }
                console.error('Barcode gagal dibuat.', error);
            }
        });
    }).catch(error => {
        console.error('Pustaka barcode gagal dimuat.', error);
        document.querySelectorAll('[data-barcode-error]').forEach(message => {
            message.hidden = false;
        });
    });
}
