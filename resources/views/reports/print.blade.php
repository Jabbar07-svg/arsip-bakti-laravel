<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Data Arsip · {{ $generatedAt->format('Y-m-d') }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;padding:24px;background:#eef3f7;color:#17324d;font-family:Arial,"Segoe UI",sans-serif;font-size:11px}.toolbar{display:flex;justify-content:space-between;max-width:1180px;margin:0 auto 14px}.toolbar a,.toolbar button{padding:9px 13px;border:1px solid #dbe5ed;border-radius:7px;background:#fff;color:#31546c;font-weight:700;text-decoration:none;cursor:pointer}.toolbar button{border-color:#087fc3;background:#087fc3;color:#fff}.sheet{max-width:1180px;margin:auto;padding:28px;background:#fff;border:1px solid #e1e8ef}.masthead{display:flex;align-items:center;gap:18px;padding-bottom:15px;border-bottom:3px solid #087fc3}.masthead img{width:115px;height:72px;object-fit:contain}.brand{color:#087fc3;font-size:10px;font-weight:800;letter-spacing:.8px;text-transform:uppercase}h1{margin:5px 0;color:#12324e;font-size:21px}.sub{color:#708499;font-size:10px}.summary{display:flex;justify-content:space-between;gap:12px;margin:16px 0;padding:11px;background:#f2f8fc;color:#45647e}.summary strong{color:#123b59}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:820px}th{background:#f1f6fa;color:#47657d;text-align:left;font-size:9px;text-transform:uppercase}th,td{padding:8px 7px;border-bottom:1px solid #e7edf2;vertical-align:top}td{font-size:9px;line-height:1.4}.code{color:#0876ad;white-space:nowrap}.signature{display:flex;justify-content:flex-end;margin-top:28px}.signature div{width:220px;text-align:center;color:#566e80;font-size:10px;line-height:1.6}
        @page{size:A4 landscape;margin:12mm}@media print{body{padding:0;background:#fff}.toolbar{display:none}.sheet{max-width:none;margin:0;padding:0;border:0}.masthead,thead{break-inside:avoid}tr{break-inside:avoid}table{min-width:0}}
        @media(max-width:650px){body{padding:10px}.sheet{padding:15px}.masthead{align-items:flex-start}.masthead img{width:76px;height:55px}.summary{flex-direction:column}}
    </style>
</head>
<body>
    <div class="toolbar"><a href="{{ route('reports.index', array_filter($filters, fn ($value) => $value !== null)) }}">Kembali ke laporan</a><button type="button" onclick="window.print()">Simpan sebagai PDF</button></div>
    <main class="sheet">
        <header class="masthead">
            <img src="{{ asset('images/bakti-logo.png') }}" alt="Logo BAKTI Komdigi">
            <div><div class="brand">Arsip BAKTI · Komdigi</div><h1>Laporan Data Arsip</h1><div class="sub">Gudang Utama · Dokumen resmi sistem pengelolaan arsip</div></div>
        </header>
        <div class="summary"><span>{{ $archives->count() }} arsip ditemukan</span><span>Dicetak {{ $generatedAt->format('d/m/Y H:i') }} WIB · {{ $user->nama }}</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>No.</th><th>QR ID</th><th>Nomor berkas</th><th>Nama / Judul</th><th>Kategori</th><th>Tanggal masuk</th><th>Lokasi</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($archives as $index => $archive)
                    <tr><td>{{ $index + 1 }}</td><td class="code">{{ $archive->qr_code }}</td><td>{{ $archive->nomor_berkas }}</td><td>{{ $archive->nama_berkas }}<br><span class="sub">{{ $archive->kode_arsip }}</span></td><td>{{ $archive->nama_kategori ?? '—' }}</td><td>{{ \Illuminate\Support\Carbon::parse($archive->tanggal_masuk)->format('d/m/Y') }}</td><td>{{ implode(' / ', array_filter([$archive->gudang, $archive->rak, $archive->lemari, $archive->box])) ?: '—' }}</td><td>{{ $archive->status }}</td></tr>
                @empty
                    <tr><td colspan="8" style="padding:30px;text-align:center">Tidak ada arsip untuk filter yang dipilih.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="signature"><div>Jakarta, {{ $generatedAt->translatedFormat('d F Y') }}<br><br><br><br>______________________<br>Petugas / Penanggung Jawab</div></div>
    </main>
</body>
</html>
