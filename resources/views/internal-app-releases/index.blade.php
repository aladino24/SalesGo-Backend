<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SalesGo · Rilis APK Internal</title>
    <style>
        :root { color-scheme: light; --blue:#1768f2; --ink:#102047; --muted:#6a7690; --line:#e6ebf5; --danger:#c73b4f; }
        * { box-sizing:border-box; } body { margin:0; background:#f4f7fc; color:var(--ink); font:14px Inter,Segoe UI,Arial,sans-serif; }
        header { background:linear-gradient(135deg,#0e4fc5,#327dff); color:#fff; padding:34px max(20px,calc((100% - 1120px)/2)); } header p { max-width:700px; margin:7px 0 0; opacity:.86; line-height:1.55; } h1 { margin:0; font-size:25px; }
        main { width:min(1120px,calc(100% - 32px)); margin:26px auto 48px; display:grid; grid-template-columns:minmax(0,1.05fr) minmax(360px,.95fr); gap:20px; align-items:start; } .card { background:#fff; border:1px solid var(--line); border-radius:18px; box-shadow:0 9px 28px rgba(25,57,113,.06); overflow:hidden; }
        .head { padding:20px 22px 14px; border-bottom:1px solid var(--line); } h2 { margin:0; font-size:17px; } .head p { color:var(--muted); margin:7px 0 0; font-size:12px; line-height:1.45; } form { padding:20px 22px 24px; } .grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; } .field { display:grid; gap:7px; margin-bottom:14px; } label { font-weight:700; font-size:12px; } input,textarea { width:100%; border:1px solid #d9e1f0; border-radius:10px; padding:11px 12px; font:inherit; color:var(--ink); background:#fff; } input:focus,textarea:focus { outline:3px solid rgba(23,104,242,.14); border-color:var(--blue); } textarea { min-height:115px; resize:vertical; } .hint { font-size:11px; color:var(--muted); line-height:1.45; }
        .checks { display:flex; flex-wrap:wrap; gap:10px 18px; margin:2px 0 18px; } .checks label { display:flex; align-items:center; gap:8px; font-weight:600; } .checks input { width:auto; accent-color:var(--blue); } button { border:0; border-radius:10px; background:var(--blue); color:white; padding:12px 16px; font:inherit; font-weight:800; cursor:pointer; width:100%; } button:hover { background:#0958da; }
        .alert { margin:16px 22px 0; border-radius:10px; padding:12px 14px; font-size:12px; } .success { color:#097443; background:#eafaf2; } .errors { color:#9e2938; background:#fff0f2; } .errors ul { margin:5px 0 0 16px; padding:0; } .releases { padding:8px 14px 14px; } .release { padding:14px 9px; display:flex; gap:11px; border-bottom:1px solid var(--line); } .release:last-child { border:0; } .badge { flex:0 0 auto; align-self:start; background:#edf4ff; color:#1768f2; border-radius:8px; padding:7px 8px; font-size:11px; font-weight:800; } .release h3 { margin:0; font-size:13px; } .release p { margin:5px 0 0; color:var(--muted); font-size:11px; line-height:1.45; white-space:pre-line; } .tag { display:inline-block; margin-top:7px; padding:3px 7px; border-radius:99px; font-size:10px; font-weight:800; } .published { color:#087945; background:#e9f9f0; } .draft { color:#9d6300; background:#fff4dc; } .mandatory { color:#a92636; background:#fff0f2; margin-left:5px; }
        .release-sidebar { position:fixed; inset:0 auto 0 0; width:245px; padding:24px 16px; background:linear-gradient(180deg,#0e4fbd,#1768f2); color:#fff; } .release-sidebar a { display:block; padding:11px 12px; margin-top:7px; border-radius:9px; color:#eaf1ff; text-decoration:none; font-size:13px; font-weight:700; } .release-sidebar a.active { background:#fff; color:#1458cb; } .release-shell { margin-left:245px; width:calc(100% - 245px); min-height:100vh; } .release-user { position:absolute; left:16px; right:16px; bottom:22px; color:#dce8ff; font-size:11px; line-height:1.5; }
        @media (max-width:820px) { main { grid-template-columns:1fr; } .release-sidebar { width:210px; } .release-shell { margin-left:210px; width:calc(100% - 210px); } } @media (max-width:640px) { .release-sidebar { position:static; width:100%; padding:14px 16px; } .release-sidebar nav { display:flex; gap:8px; overflow:auto; } .release-sidebar a { white-space:nowrap; margin:0; } .release-user { display:none; } .release-shell { margin:0; width:100%; } header { padding:26px 20px; } main { width:calc(100% - 24px); margin-top:14px; } } @media (max-width:450px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<aside class="release-sidebar"><strong style="font-size:19px">SalesGo Admin</strong><p style="font-size:11px;opacity:.8;line-height:1.5">{{ $actor?->name }}<br>Role IT</p><nav><a href="{{ route('internal.master-data.index') }}">Master Data</a><a class="active" href="{{ route('internal.app-releases.index') }}">Upload APK</a></nav><div class="release-user">{{ $actor?->employee_code }}</div></aside><div class="release-shell"><header><h1>Rilis APK Internal SalesGo</h1><p>Unggah artefak Android yang telah ditandatangani. File disimpan privat; aplikasi mobile mengunduhnya hanya setelah verifikasi MD5.</p></header>
<main>
    <section class="card">
        <div class="head"><h2>Upload pembaruan Android</h2><p>Build number harus lebih tinggi dari rilis sebelumnya. Jangan mengganti application ID atau signing key.</p></div>
        @if (session('success')) <div class="alert success">{{ session('success') }}</div> @endif
        @if ($errors->any())<div class="alert errors"><strong>Upload belum berhasil.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="post" action="{{ route('internal.app-releases.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="field"><label for="apk">File APK <span style="color:var(--danger)">*</span></label><input id="apk" name="apk" type="file" accept=".apk,application/vnd.android.package-archive" required><span class="hint">Maksimal 200 MB. Gunakan APK release, bukan source code atau App Bundle (.aab).</span></div>
            <div class="grid">
                <div class="field"><label for="version_name">Versi aplikasi <span style="color:var(--danger)">*</span></label><input id="version_name" name="version_name" value="{{ old('version_name') }}" placeholder="Contoh: 1.0.1" required><span class="hint">Tampil untuk pengguna.</span></div>
                <div class="field"><label for="version_code">Build number <span style="color:var(--danger)">*</span></label><input id="version_code" name="version_code" type="number" min="1" value="{{ old('version_code') }}" placeholder="Contoh: 2" required><span class="hint">Harus unik dan meningkat.</span></div>
            </div>
            <div class="field"><label for="release_notes">Keterangan rilis</label><textarea id="release_notes" name="release_notes" placeholder="Contoh: Perbaikan check-in dan katalog produk.">{{ old('release_notes') }}</textarea></div>
            <div class="checks"><label><input type="checkbox" name="is_published" value="1" {{ old('is_published', true) ? 'checked' : '' }}> Publikasikan sekarang</label><label><input type="checkbox" name="is_mandatory" value="1" {{ old('is_mandatory') ? 'checked' : '' }}> Wajib update</label></div>
            <button type="submit">Upload APK dan simpan rilis</button>
        </form>
    </section>
    <aside class="card">
        <div class="head"><h2>Riwayat rilis</h2><p>MD5 dan SHA-256 dihitung oleh server saat upload.</p></div>
        <div class="releases">
            @forelse ($releases as $release)
                <article class="release"><div class="badge">{{ $release->version_code }}</div><div><h3>v{{ $release->version_name }} · {{ number_format($release->size_bytes / 1048576, 1) }} MB</h3><p>{{ $release->original_name }}<br>{{ $release->created_at?->format('d M Y H:i') }}@if($release->release_notes)<br>{{ $release->release_notes }}@endif</p><span class="tag {{ $release->is_published ? 'published' : 'draft' }}">{{ $release->is_published ? 'Published' : 'Draft' }}</span>@if($release->is_mandatory)<span class="tag mandatory">Wajib update</span>@endif</div></article>
            @empty
                <p style="padding:16px;color:var(--muted);font-size:12px">Belum ada APK yang diunggah.</p>
            @endforelse
        </div>
    </aside>
</main></div>
</body>
</html>
