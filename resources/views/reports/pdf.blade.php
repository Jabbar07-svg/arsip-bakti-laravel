<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Data Arsip</title>
    <style>
        @page{size:A4 landscape;margin:16mm}
        body{font-family:DejaVu Sans,sans-serif;color:#17324d;font-size:9px}
        .masthead{display:table;width:100%;padding:0 0 12px;border-bottom:3px solid #087fc3}
        .masthead-logo{display:table-cell;width:110px;padding-right:16px;vertical-align:middle}
        .masthead-logo img{display:block;width:100px;height:auto}
        .masthead-copy{display:table-cell;vertical-align:middle}
        .brand{color:#087fc3;font-size:9px;font-weight:bold;letter-spacing:1px;text-transform:uppercase}
        h1{margin:5px 0;color:#12324e;font-size:19px}
        .sub,.meta{color:#708499;font-size:8px}
        .summary{margin:12px 0;padding:9px;background:#f2f8fc;color:#45647e}
        table{width:100%;border-collapse:collapse}
        th{background:#f1f6fa;color:#47657d;text-align:left;font-size:7px;text-transform:uppercase}
        th,td{padding:6px 5px;border-bottom:1px solid #e3ebf0;vertical-align:top}
        td{font-size:7px;line-height:1.35}
        .code{color:#0876ad;white-space:nowrap}
        .footer{margin-top:14px;color:#708499;font-size:8px;text-align:right}
        tr{page-break-inside:avoid}
    </style>
</head>
<body>
    <header class="masthead">
        <div class="masthead-logo"><img src="{{ $logoDataUri }}" alt="Logo BAKTI Komdigi"></div>
        <div class="masthead-copy">
            <div class="brand">Arsip BAKTI · Komdigi</div>
            <h1>Laporan Data Arsip</h1>
            <div class="sub">Gudang Utama · Dokumen resmi sistem pengelolaan arsip</div>
        </div>
    </header>
    <table>
        <thead>
            <tr><th>No.</th><th>QR ID</th><th>Nomor berkas</th><th>Nama / Judul</th><th>Kategori</th><th>Tanggal masuk</th><th>Lokasi</th><th>Status</th></tr>
        </thead>
        <tbody>
        @forelse ($archives as $index => $archive)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td class="code">{{ $archive->qr_code }}</td>
                <td>{{ $archive->nomor_berkas }}</td>
                <td>{{ $archive->nama_berkas }}<br><span class="sub">{{ $archive->kode_arsip }}</span></td>
                <td>{{ $archive->nama_kategori ?? '—' }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($archive->tanggal_masuk)->format('d/m/Y') }}</td>
                <td>{{ implode(' / ', array_filter([$archive->gudang, $archive->rak, $archive->lemari, $archive->box])) ?: '—' }}</td>
                <td>{{ $archive->status }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="padding:24px;text-align:center">Tidak ada arsip untuk filter yang dipilih.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="footer">Dokumen dibuat oleh Sistem Arsip BAKTI Komdigi · {{ $generatedAt->format('d/m/Y H:i') }} WIB</div>
</body>
</html>
