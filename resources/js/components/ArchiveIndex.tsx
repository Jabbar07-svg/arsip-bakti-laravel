interface ArchiveRow {
    id: number;
    name: string;
    number: string;
    code: string;
    category: string;
    date: string;
    location: string;
    qrCode: string;
    status: string;
    url: string;
}

interface ArchiveLocation {
    id: number;
    label: string;
}

interface ArchiveIndexData {
    archives: ArchiveRow[];
    filters: {
        q: string;
        status: string;
        lokasi: number | string | null;
    };
    statusOptions: string[];
    locations: ArchiveLocation[];
    archiveCount: number;
    canManageArchives: boolean;
    createUrl: string | null;
    indexUrl: string;
    previousUrl: string | null;
    nextUrl: string | null;
    currentPage: number;
    lastPage: number;
    hasPages: boolean;
    flash: {
        success: string | null;
        error: string | null;
    };
}

const statusClasses: Record<string, string> = {
    Tersimpan: 'is-stored',
    Dipinjam: 'is-loaned',
    Diproses: 'is-processing',
    Hilang: 'is-missing',
    Nonaktif: 'is-inactive',
};

function formatCount(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

export function ArchiveIndex({ data }: { data: ArchiveIndexData }) {
    const hasFilters = Boolean(data.filters.q || data.filters.status || data.filters.lokasi);

    return (
        <main className="page archive-index">
            <header className="archive-index-heading">
                <div>
                    <span className="archive-eyebrow">Inventaris · Arsip</span>
                    <h1>Data Arsip</h1>
                    <p className="page-description">
                        Temukan dan tinjau arsip sesuai cakupan akses akun Anda.
                    </p>
                </div>
                {data.createUrl && (
                    <a className="button button-primary archive-create-button" href={data.createUrl}>
                        <span aria-hidden="true">+</span> Tambah arsip
                    </a>
                )}
            </header>

            {data.flash.success && (
                <div className="archive-flash archive-flash-success" role="status">
                    {data.flash.success}
                </div>
            )}
            {data.flash.error && (
                <div className="archive-flash archive-flash-error" role="alert">
                    {data.flash.error}
                </div>
            )}

            <div className="archive-index-summary">
                <span className="archive-summary-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5zM8 4v16m4-12h4m-4 4h4m-4 4h4" />
                    </svg>
                </span>
                <div>
                    <strong>{formatCount(data.archiveCount)} arsip</strong>
                    <span>Jumlah data yang sesuai dengan akses dan filter aktif</span>
                </div>
            </div>

            <form className="archive-search-panel" method="get" action={data.indexUrl}>
                <div className="archive-search-field">
                    <label htmlFor="archive-search">Cari arsip</label>
                    <div className="archive-search-input">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <circle cx="10.8" cy="10.8" r="6.3" />
                            <path d="m16 16 4 4" />
                        </svg>
                        <input
                            id="archive-search"
                            type="search"
                            name="q"
                            defaultValue={data.filters.q}
                            placeholder="Nama, nomor, kode, QR, kategori, atau lokasi"
                            autoComplete="off"
                        />
                    </div>
                </div>
                <div className="archive-filter-field">
                    <label htmlFor="archive-status">Status</label>
                    <select id="archive-status" name="status" defaultValue={data.filters.status}>
                        <option value="">Semua status</option>
                        {data.statusOptions.map((status) => (
                            <option value={status} key={status}>
                                {status}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="archive-filter-field">
                    <label htmlFor="archive-location">Lokasi</label>
                    <select
                        id="archive-location"
                        name="lokasi"
                        defaultValue={data.filters.lokasi ?? ''}
                    >
                        <option value="">Semua lokasi</option>
                        {data.locations.map((location) => (
                            <option value={location.id} key={location.id}>
                                {location.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="archive-filter-actions">
                    <button className="button button-primary" type="submit">
                        Tampilkan
                    </button>
                    {hasFilters && (
                        <a className="archive-reset-link" href={data.indexUrl}>
                            Reset
                        </a>
                    )}
                </div>
            </form>

            <section className="archive-results panel" aria-labelledby="archive-results-title">
                <div className="archive-results-heading">
                    <div>
                        <h2 id="archive-results-title">Daftar arsip</h2>
                        <p>Gunakan detail untuk melihat metadata, lampiran, QR, dan riwayat.</p>
                    </div>
                    <span className="archive-results-count">{formatCount(data.archiveCount)} hasil</span>
                </div>

                {data.archives.length === 0 ? (
                    <div className="archive-empty-state">
                        <span className="archive-empty-icon" aria-hidden="true">⌕</span>
                        <h3>{hasFilters ? 'Arsip tidak ditemukan' : 'Belum ada arsip'}</h3>
                        <p>
                            {hasFilters
                                ? 'Coba periksa kata kunci atau ubah filter yang digunakan.'
                                : 'Arsip yang dapat Anda akses akan muncul di halaman ini.'}
                        </p>
                        {hasFilters && (
                            <a className="button" href={data.indexUrl}>
                                Hapus semua filter
                            </a>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="archive-table-wrap">
                            <table className="archive-table archive-index-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Arsip</th>
                                        <th scope="col">Kategori</th>
                                        <th scope="col">Tanggal masuk</th>
                                        <th scope="col">Lokasi</th>
                                        <th scope="col">QR ID</th>
                                        <th scope="col">Status</th>
                                        <th scope="col"><span className="visually-hidden">Aksi</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.archives.map((archive) => (
                                        <tr key={archive.id}>
                                            <td>
                                                <a className="archive-row-name" href={archive.url}>
                                                    {archive.name}
                                                </a>
                                                <span className="archive-row-reference">
                                                    {archive.number} <span aria-hidden="true">·</span> {archive.code}
                                                </span>
                                            </td>
                                            <td>{archive.category || '—'}</td>
                                            <td>{archive.date}</td>
                                            <td>{archive.location || '—'}</td>
                                            <td><span className="archive-qr-id">{archive.qrCode}</span></td>
                                            <td>
                                                <span className={`archive-status ${statusClasses[archive.status] ?? ''}`}>
                                                    <span aria-hidden="true" />
                                                    {archive.status}
                                                </span>
                                            </td>
                                            <td>
                                                <a className="archive-open-link" href={archive.url}>
                                                    Detail <span aria-hidden="true">→</span>
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="archive-mobile-list">
                            {data.archives.map((archive) => (
                                <article className="archive-mobile-card" key={archive.id}>
                                    <div className="archive-mobile-title-row">
                                        <div className="archive-mobile-title">
                                            <a href={archive.url}>{archive.name}</a>
                                            <span>{archive.number} · {archive.code}</span>
                                        </div>
                                        <span className={`archive-status ${statusClasses[archive.status] ?? ''}`}>
                                            <span aria-hidden="true" />
                                            {archive.status}
                                        </span>
                                    </div>
                                    <dl className="archive-mobile-meta">
                                        <div><dt>Kategori</dt><dd>{archive.category || '—'}</dd></div>
                                        <div><dt>Tanggal</dt><dd>{archive.date}</dd></div>
                                        <div><dt>Lokasi</dt><dd>{archive.location || '—'}</dd></div>
                                        <div><dt>QR ID</dt><dd>{archive.qrCode}</dd></div>
                                    </dl>
                                    <a className="archive-mobile-action" href={archive.url}>
                                        Lihat detail arsip <span aria-hidden="true">→</span>
                                    </a>
                                </article>
                            ))}
                        </div>
                    </>
                )}

                {data.hasPages && (
                    <nav className="archive-pagination" aria-label="Halaman data arsip">
                        <span>Halaman {data.currentPage} dari {data.lastPage}</span>
                        <div>
                            {data.previousUrl ? (
                                <a className="button" href={data.previousUrl}>Sebelumnya</a>
                            ) : (
                                <span className="button is-disabled" aria-disabled="true">Sebelumnya</span>
                            )}
                            {data.nextUrl ? (
                                <a className="button" href={data.nextUrl}>Berikutnya</a>
                            ) : (
                                <span className="button is-disabled" aria-disabled="true">Berikutnya</span>
                            )}
                        </div>
                    </nav>
                )}
            </section>
            <p className="archive-access-note">
                Informasi yang ditampilkan mengikuti hak akses akun. Arsip di luar cakupan Anda tidak ditampilkan.
            </p>
        </main>
    );
}
