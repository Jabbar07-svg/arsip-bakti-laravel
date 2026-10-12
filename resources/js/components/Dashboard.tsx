import { useEffect, useState } from 'react';

type RecentArchive = {
    id: number;
    qrCode: string;
    number: string;
    name: string;
    category: string;
    date: string;
    status: string;
    url: string;
};

type CategorySlice = {
    name: string;
    total: number;
};

export type DashboardProps = {
    userName: string;
    year: number;
    currentMonth: number;
    monthName: string;
    dateLabel: string;
    timeLabel: string;
    timezone: string;
    totalArchives: number;
    thisMonth: number;
    thisYear: number;
    locationCount: number;
    statusCounts: Record<string, number>;
    monthly: number[];
    monthlyLoans: number[];
    activeLoans: number;
    overdueLoans: number;
    categoryDistribution: CategorySlice[];
    recentArchives: RecentArchive[];
    archivesUrl: string;
    createArchiveUrl: string | null;
    createArchiveLabel: string | null;
    reportsUrl: string | null;
    scannerUrl: string;
    labelsUrl: string | null;
    loansUrl: string | null;
};

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const categoryColors = ['#146ee8', '#13b8a6', '#ff9e32', '#ed5a71', '#8662dc', '#8aa1b3', '#b6c7d2'];
const numberFormat = new Intl.NumberFormat('id-ID');

function statusClass(status: string): string {
    return `status-${status.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
}

function linePoints(values: number[], maxValue: number): string {
    return values.map((value, index) => {
        const x = 38 + (index * 644) / Math.max(1, values.length - 1);
        const y = 168 - (value / maxValue) * 132;
        return `${x},${y}`;
    }).join(' ');
}

function ArchiveActivityChart({ data }: { data: DashboardProps }) {
    const visibleArchives = data.monthly.slice(0, data.currentMonth);
    const visibleLoans = data.monthlyLoans.slice(0, data.currentMonth);
    const visibleMonthNames = monthNames.slice(0, data.currentMonth);
    const maxValue = Math.max(1, ...visibleArchives, ...visibleLoans);
    const archivePoints = linePoints(visibleArchives, maxValue);
    const loanPoints = linePoints(visibleLoans, maxValue);
    const hasValues = visibleArchives.some(Boolean) || visibleLoans.some(Boolean);

    if (!hasValues) {
        return (
            <div className="dashboard-empty dashboard-chart-empty" role="status">
                <strong>Belum ada aktivitas pada {data.year}</strong>
                <span>Data arsip dan peminjaman akan terlihat setelah transaksi tercatat.</span>
            </div>
        );
    }

    return (
        <div className="activity-chart-wrap">
            <svg className="activity-chart" viewBox="0 0 720 210" role="img" aria-labelledby="activity-chart-title activity-chart-desc">
                <title id="activity-chart-title">Aktivitas arsip dan peminjaman</title>
                <desc id="activity-chart-desc">Jumlah arsip masuk dan transaksi peminjaman per bulan pada tahun {data.year}.</desc>
                {[36, 80, 124, 168].map((y) => (
                    <g key={y}>
                        <line x1="38" x2="682" y1={y} y2={y} className="chart-grid-line" />
                        <text x="29" y={y + 3} textAnchor="end" className="chart-axis-label">
                            {numberFormat.format(Math.round((168 - y) / 132 * maxValue))}
                        </text>
                    </g>
                ))}
                <polygon points={`38,168 ${archivePoints} 682,168`} className="chart-archive-area" />
                <polyline points={archivePoints} className="chart-archive-line" />
                <polyline points={loanPoints} className="chart-loan-line" />
                {visibleArchives.map((value, index) => {
                    const x = 38 + (index * 644) / Math.max(1, visibleArchives.length - 1);
                    const archiveY = 168 - (value / maxValue) * 132;
                    const loanY = 168 - ((visibleLoans[index] ?? 0) / maxValue) * 132;
                    return (
                        <g key={visibleMonthNames[index]}>
                            <circle cx={x} cy={archiveY} r="3.4" className="chart-archive-dot">
                                <title>{visibleMonthNames[index]}: {value} arsip</title>
                            </circle>
                            <circle cx={x} cy={loanY} r="3" className="chart-loan-dot">
                                <title>{visibleMonthNames[index]}: {visibleLoans[index] ?? 0} peminjaman</title>
                            </circle>
                            {(index % 2 === 0 || index === visibleArchives.length - 1) && (
                                <text x={x} y="195" textAnchor="middle" className="chart-axis-label">
                                    {visibleMonthNames[index]}
                                </text>
                            )}
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

function CategoryChart({ categories, total }: { categories: CategorySlice[]; total: number }) {
    const totalCategorized = categories.reduce((sum, item) => sum + item.total, 0);
    let offset = 0;
    const segments = categories.map((category, index) => {
        const percentage = totalCategorized > 0 ? (category.total / totalCategorized) * 100 : 0;
        const segment = {
            ...category,
            color: categoryColors[index % categoryColors.length],
            start: offset,
            end: offset + percentage,
            percentage: Math.round(percentage),
        };
        offset += percentage;
        return segment;
    });
    const background = segments.length
        ? `conic-gradient(${segments.map((item) => `${item.color} ${item.start}% ${item.end}%`).join(', ')})`
        : '#e8eef2';

    return (
        <div className="category-chart-layout">
            <div className="category-donut" style={{ background }} role="img" aria-label={`Distribusi ${numberFormat.format(total)} arsip pada kategori`}>
                <div className="category-donut-center">
                    <strong>{numberFormat.format(total)}</strong>
                    <span>Total arsip</span>
                </div>
            </div>
            {segments.length > 0 ? (
                <ul className="category-legend">
                    {segments.map((category) => (
                        <li key={category.name}>
                            <span className="category-legend-dot" style={{ backgroundColor: category.color }} aria-hidden="true" />
                            <span className="category-legend-name" title={category.name}>{category.name}</span>
                            <strong>{category.percentage}%</strong>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="category-empty" role="status">Belum ada data kategori untuk ditampilkan.</p>
            )}
        </div>
    );
}

function StatIcon({ kind }: { kind: 'archive' | 'active' | 'loan' | 'due' }) {
    if (kind === 'archive') {
        return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3.75h8l4 4V20.25H6zM14 3.75v4h4M9 12h6m-6 3.5h6" /></svg>;
    }
    if (kind === 'active') {
        return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5h16v12H4zM3 4.5h18v3H3zm6 7h6m-3-3v6" /></svg>;
    }
    if (kind === 'loan') {
        return <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5" /><path d="M12 7v5l3 2" /></svg>;
    }
    return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5 21 20H3zM12 9v5m0 3h.01" /></svg>;
}

function Dashboard({ data }: { data: DashboardProps }) {
    const [dateLabel, setDateLabel] = useState(data.dateLabel);
    const [timeLabel, setTimeLabel] = useState(data.timeLabel);
    const timezoneLabels: Record<string, string> = {
        'Asia/Jakarta': 'WIB',
        'Asia/Makassar': 'WITA',
        'Asia/Jayapura': 'WIT',
    };

    useEffect(() => {
        const dateFormatter = new Intl.DateTimeFormat('id-ID', {
            timeZone: data.timezone,
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
        const timeFormatter = new Intl.DateTimeFormat('id-ID', {
            timeZone: data.timezone,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23',
        });
        const updateClock = () => {
            const now = new Date();
            setDateLabel(dateFormatter.format(now));
            setTimeLabel(`${timeFormatter.format(now)} ${timezoneLabels[data.timezone] ?? data.timezone}`);
        };

        updateClock();
        const interval = window.setInterval(updateClock, 1000);
        return () => window.clearInterval(interval);
    }, [data.timezone]);

    const actions = [
        data.createArchiveUrl && data.createArchiveLabel
            ? { label: data.createArchiveLabel, href: data.createArchiveUrl, kind: 'add' }
            : null,
        { label: 'Scan QR', href: data.scannerUrl, kind: 'scan' },
        data.labelsUrl ? { label: 'Cetak label QR', href: data.labelsUrl, kind: 'label' } : null,
        data.loansUrl ? { label: 'Lihat peminjaman', href: data.loansUrl, kind: 'loan' } : null,
        data.reportsUrl ? { label: 'Buka laporan', href: data.reportsUrl, kind: 'report' } : null,
    ].filter((action): action is { label: string; href: string; kind: string } => action !== null);

    return (
        <main className="page dashboard-page">
            <section className="dashboard-hero dashboard-hero-reference" aria-labelledby="dashboard-welcome">
                <div className="hero-copy">
                    <div className="hero-kicker">SELAMAT DATANG</div>
                    <h1 id="dashboard-welcome">Sistem Arsip BAKTI Komdigi</h1>
                    <p>Kelola arsip digital dengan lebih mudah, aman, dan terstruktur.</p>
                    <span className="hero-supporting">Mendukung tata kelola informasi yang transparan dan efisien.</span>
                </div>
                <div className="dashboard-date-chip">
                    <span className="date-chip-icon" aria-hidden="true">▦</span>
                    <span><strong>{dateLabel}</strong><small>{timeLabel}</small></span>
                </div>
                <svg className="hero-architecture" viewBox="0 0 610 205" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                    <defs>
                        <linearGradient id="building-glass" x1="0" x2="1" y1="0" y2="1">
                            <stop offset="0" stopColor="#f8fcff" stopOpacity=".78" />
                            <stop offset="1" stopColor="#71b8df" stopOpacity=".14" />
                        </linearGradient>
                    </defs>
                    <path d="M114 205V69l103-42v178zm109 0V44l112-37v198zm118 0V83l94-48v170z" fill="url(#building-glass)" stroke="#e8f6ff" strokeOpacity=".5" />
                    {[139, 164, 189, 247, 276, 305, 334, 361, 386, 412].map((x) => (
                        <line key={x} x1={x} x2={x} y1={x < 215 ? 75 : x < 340 ? 58 : 91} y2="202" stroke="#f0f9ff" strokeOpacity=".28" />
                    ))}
                    {[93, 118, 143, 168].map((y) => (
                        <path key={y} d={`M114 ${y}h103m6 ${y - 7}h112m6 ${y + 4}h94`} stroke="#f0f9ff" strokeOpacity=".24" />
                    ))}
                    <path d="M66 205h442" stroke="#fff" strokeOpacity=".5" />
                </svg>
            </section>

            <section className="dashboard-stat-grid dashboard-reference-stats" aria-label="Ringkasan arsip dan peminjaman">
                <article className="dashboard-stat-card stat-blue">
                    <span className="stat-icon"><StatIcon kind="archive" /></span>
                    <div><span className="stat-label">Total Arsip</span><strong>{numberFormat.format(data.totalArchives)}</strong><small>Seluruh arsip yang dapat diakses</small></div>
                </article>
                <article className="dashboard-stat-card stat-green">
                    <span className="stat-icon"><StatIcon kind="active" /></span>
                    <div><span className="stat-label">Arsip Aktif</span><strong>{numberFormat.format(data.statusCounts.Tersimpan ?? 0)}</strong><small>Status tersimpan dan tersedia</small></div>
                </article>
                <article className="dashboard-stat-card stat-amber">
                    <span className="stat-icon"><StatIcon kind="loan" /></span>
                    <div><span className="stat-label">Peminjaman</span><strong>{numberFormat.format(data.activeLoans)}</strong><small>Transaksi masih dipinjam</small></div>
                </article>
                <article className="dashboard-stat-card stat-red">
                    <span className="stat-icon"><StatIcon kind="due" /></span>
                    <div><span className="stat-label">Jatuh Tempo</span><strong>{numberFormat.format(data.overdueLoans)}</strong><small>Belum dikembalikan melewati tanggal</small></div>
                </article>
            </section>

            <section className="dashboard-reference-grid">
                <article className="panel dashboard-chart-panel dashboard-activity-panel">
                    <div className="dashboard-panel-heading">
                        <div><h2>Aktivitas Arsip</h2><p>Catatan arsip dan transaksi peminjaman · {data.year}</p></div>
                        <div className="chart-legend" aria-label="Keterangan grafik">
                            <span><i className="legend-archive" />Arsip ditambahkan</span>
                            <span><i className="legend-loan" />Peminjaman</span>
                        </div>
                    </div>
                    <ArchiveActivityChart data={data} />
                </article>

                <article className="panel dashboard-category-panel">
                    <div className="dashboard-panel-heading">
                        <div><h2>Distribusi Kategori</h2><p>Komposisi arsip saat ini</p></div>
                    </div>
                    <CategoryChart categories={data.categoryDistribution} total={data.totalArchives} />
                </article>

                <aside className="panel dashboard-quick-panel">
                    <div className="dashboard-panel-heading"><div><h2>Aksi Cepat</h2><p>Pintasan pekerjaan</p></div></div>
                    <nav className="quick-action-list" aria-label="Aksi cepat">
                        {actions.map((action) => (
                            <a href={action.href} key={action.label}>
                                <span className={`quick-action-icon icon-${action.kind}`} aria-hidden="true">
                                    {action.kind === 'add' ? '+' : action.kind === 'scan' ? '⌗' : action.kind === 'label' ? '▤' : action.kind === 'loan' ? '◷' : '▥'}
                                </span>
                                <span>{action.label}</span>
                                <span className="quick-action-arrow" aria-hidden="true">›</span>
                            </a>
                        ))}
                    </nav>
                </aside>
            </section>

            <section className="panel dashboard-recent-panel dashboard-reference-recent">
                <div className="dashboard-panel-heading recent-heading">
                    <div><h2>Arsip Terbaru</h2><p>Arsip terakhir yang ditambahkan dan dapat Anda akses.</p></div>
                    <a className="text-link" href={data.archivesUrl}>Lihat semua <span aria-hidden="true">→</span></a>
                </div>
                {data.recentArchives.length === 0 ? (
                    <div className="dashboard-empty" role="status">
                        <strong>Belum ada arsip yang tercatat</strong>
                        <span>Arsip baru akan muncul di sini setelah disimpan.</span>
                    </div>
                ) : (
                    <>
                        <div className="dashboard-recent-table-wrap">
                            <table className="report-table dashboard-reference-table">
                                <thead><tr><th>No</th><th>Kode Arsip</th><th>Judul</th><th>Kategori</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
                                <tbody>{data.recentArchives.map((archive, index) => (
                                    <tr key={archive.id}>
                                        <td>{index + 1}</td>
                                        <td><span className="qr-id">{archive.qrCode}</span></td>
                                        <td><a className="archive-title" href={archive.url}>{archive.name}</a></td>
                                        <td>{archive.category || '—'}</td>
                                        <td>{archive.date}</td>
                                        <td><span className={`status ${statusClass(archive.status)}`}>{archive.status}</span></td>
                                        <td><a className="recent-view-action" href={archive.url} aria-label={`Lihat ${archive.name}`}>Lihat</a></td>
                                    </tr>
                                ))}</tbody>
                            </table>
                        </div>
                        <div className="dashboard-recent-cards">
                            {data.recentArchives.map((archive) => (
                                <article className="dashboard-recent-card" key={archive.id}>
                                    <div className="dashboard-recent-card-heading"><a href={archive.url}>{archive.name}</a><span className={`status ${statusClass(archive.status)}`}>{archive.status}</span></div>
                                    <p>{archive.qrCode} · {archive.category || 'Tanpa kategori'}</p>
                                    <div><span>{archive.number}</span><time>{archive.date}</time></div>
                                    <a className="mobile-recent-detail" href={archive.url}>Lihat detail arsip <span aria-hidden="true">→</span></a>
                                </article>
                            ))}
                        </div>
                    </>
                )}
            </section>
            <p className="dashboard-reference-footnote">
                Ringkasan diperbarui dari data yang tersedia bagi akun {data.userName}. Lokasi tersimpan: {numberFormat.format(data.locationCount)}.
            </p>
        </main>
    );
}

export function DashboardApp({ data }: { data: DashboardProps }) {
    return <Dashboard data={data} />;
}
