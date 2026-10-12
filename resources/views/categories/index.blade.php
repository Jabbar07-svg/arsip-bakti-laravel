@extends('layouts.app')

@section('title', 'Kategori Arsip')

@section('content')
<main class="page">
    <div class="page-heading-row">
        <div>
            <div class="eyebrow">Master Data</div>
            <h1>Kategori Arsip</h1>
            <p class="page-description">Kelola kategori tanpa menghapus referensi yang masih digunakan arsip.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="notice-success" role="status">{{ session('success') }}</div>
    @endif

    <div class="category-layout">
        <section class="panel category-form">
            <h2>Tambah kategori</h2>
            <p>Gunakan nama yang mudah dikenali petugas.</p>
            <form method="post" action="{{ route('categories.store') }}">
                @csrf
                <label for="nama_kategori">Nama kategori</label>
                <input id="nama_kategori" name="nama_kategori" maxlength="150" value="{{ old('nama_kategori') }}" placeholder="Contoh: Kepegawaian" required>
                @error('nama_kategori')<span class="form-error">{{ $message }}</span>@enderror
                <button class="button button-primary" type="submit">Simpan kategori</button>
            </form>
        </section>

        <section class="panel category-list">
            <div class="panel-heading">
                <div><h2>Daftar kategori</h2><p>{{ number_format($categories->count(), 0, ',', '.') }} kategori terdaftar</p></div>
            </div>
            @if ($categories->isEmpty())
                <div class="empty-state">Belum ada kategori.</div>
            @else
                <div class="table-scroll">
                    <table class="report-table">
                        <thead><tr><th>Nama kategori</th><th>Jumlah arsip</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td><strong>{{ $category->nama_kategori }}</strong></td>
                                <td>{{ number_format((int) $category->jumlah_arsip, 0, ',', '.') }}</td>
                                <td><span class="status status-{{ \Illuminate\Support\Str::slug($category->status) }}">{{ $category->status }}</span></td>
                                <td>
                                    <form method="post" action="{{ route('categories.destroy', $category->id) }}" onsubmit="return confirm('Hapus atau nonaktifkan kategori ini?')">
                                        @csrf
                                        @method('delete')
                                        <button class="button category-delete" type="submit">{{ (int) $category->jumlah_arsip > 0 ? 'Nonaktifkan' : 'Hapus' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</main>
<style>
.category-layout{display:grid;grid-template-columns:minmax(250px,.7fr) minmax(0,1.8fr);gap:16px;align-items:start}.category-form{padding:20px}.category-form h2{margin:0;color:#1c405b;font-size:15px}.category-form>p{margin:6px 0 16px;color:#8295a3;font-size:11px}.category-form form{display:grid;gap:9px}.category-form label{color:#49677c;font-size:11px;font-weight:700}.category-form input{min-height:42px;padding:0 11px;border:1px solid #dce6ec;border-radius:8px;font-size:13px}.category-form .button{margin-top:4px}.category-list{overflow:hidden}.category-delete{min-height:33px;padding:0 10px;color:#a43b35;font-size:10px}.category-list .report-table{min-width:570px}
@media(max-width:780px){.category-layout{grid-template-columns:1fr}.category-form{padding:16px}.category-list{margin-top:0}}
</style>
@endsection
