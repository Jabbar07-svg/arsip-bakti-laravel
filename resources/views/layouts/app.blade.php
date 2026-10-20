<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (() => {
            let savedTheme = null;
            try {
                savedTheme = localStorage.getItem('arsip-bakti-theme');
            } catch (error) {
                console.error('Preferensi tema tidak dapat dibaca.', error);
            }
            document.documentElement.dataset.theme = savedTheme === 'dark' || savedTheme === 'light'
                ? savedTheme
                : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        })();
    </script>
    @vite('resources/js/app.js')
    <meta name="theme-color" content="#0b4568">
    <link rel="icon" type="image/png" href="{{ asset('images/bakti-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/bakti-logo.png') }}">
    <title>@yield('title', 'Arsip BAKTI') · {{ config('app.name') }}</title>
    <style>
        :root{font-family:Inter,"Segoe UI",Arial,sans-serif;color:#173b58;background:#f4f7f9;font-synthesis:none;text-rendering:optimizeLegibility;-webkit-font-smoothing:antialiased}
        *{box-sizing:border-box}
        body{margin:0;min-width:320px;min-height:100vh}
        a{color:#087fc3;text-decoration:none}
        button,input{font:inherit}
        .topbar{position:sticky;top:0;z-index:2;display:flex;align-items:center;gap:18px;min-height:68px;padding:10px max(24px,calc((100vw - 1240px)/2));border-bottom:1px solid #e3ebf0;background:rgba(255,255,255,.96);backdrop-filter:blur(12px)}
        .brand{display:flex;align-items:center;gap:11px;min-width:210px;color:#173b58}
        .brand-logo{display:block;width:82px;height:48px;flex:0 0 auto;object-fit:contain}
        .brand-title{display:block;font-size:15px;font-weight:800;letter-spacing:-.2px}
        .brand-subtitle{display:block;margin-top:2px;color:#7f94a4;font-size:10px}
        .main-nav{display:flex;align-items:center;gap:5px}
        .main-nav a{padding:9px 11px;border-radius:8px;color:#49677c;font-size:11px;font-weight:700;white-space:nowrap}
        .main-nav a:hover,.main-nav a.active{background:#eff7fa;color:#087fc3}
        .topbar-spacer{flex:1}
        .user-label{max-width:190px;overflow:hidden;color:#49677c;font-size:12px;font-weight:700;text-overflow:ellipsis;white-space:nowrap}
        .button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 15px;border:1px solid #dce6ec;border-radius:9px;background:#fff;color:#36566e;font-size:12px;font-weight:700;cursor:pointer}
        .button:hover{border-color:#a9ccdc;background:#f8fbfc}
        .button-primary{border-color:#087fc3;background:#087fc3;color:#fff}
        .button-primary:hover{border-color:#076ea9;background:#076ea9;color:#fff}
        :focus-visible{outline:3px solid rgba(8,127,195,.3);outline-offset:2px}
        .page{width:min(1240px,100%);margin:0 auto;padding:34px 24px 60px}
        .eyebrow{display:flex;align-items:center;gap:8px;color:#087fc3;font-size:10px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase}
        .eyebrow:before{width:19px;height:3px;border-radius:3px;background:#087fc3;content:""}
        h1{margin:8px 0 7px;color:#143a56;font-size:clamp(26px,3.5vw,36px);letter-spacing:-1px}
        .page-description{max-width:700px;margin:0;color:#748a9a;font-size:13px;line-height:1.65}
        .panel{margin-top:22px;border:1px solid #e0e9ef;border-radius:14px;background:#fff;box-shadow:0 8px 28px rgba(18,56,80,.055)}
        .panel-heading{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:18px 20px;border-bottom:1px solid #edf1f4}
        .panel-heading h2{margin:0;color:#1c405b;font-size:15px}
        .panel-heading p{margin:4px 0 0;color:#8295a3;font-size:11px}
        .count-badge{flex:none;padding:7px 10px;border-radius:99px;background:#eff7fa;color:#286183;font-size:11px;font-weight:700}
        .archive-table-wrap{overflow-x:auto}
        .archive-table{width:100%;border-collapse:collapse}
        .archive-table th{padding:12px 16px;background:#f7f9fa;color:#718696;font-size:9px;letter-spacing:.6px;text-align:left;text-transform:uppercase;white-space:nowrap}
        .archive-table td{padding:13px 16px;border-bottom:1px solid #eef2f4;color:#486477;font-size:11px;vertical-align:top}
        .archive-table tr:last-child td{border-bottom:0}
        .archive-title{color:#1c405b;font-size:12px;font-weight:700}
        .archive-detail{margin-top:4px;color:#8193a0;font-size:10px}
        .qr-id{color:#0876b3;font-size:10px;font-weight:700;white-space:nowrap}
        .status{display:inline-flex;padding:5px 8px;border-radius:20px;background:#edf8f2;color:#187653;font-size:10px;font-weight:700;white-space:nowrap}
        .empty-state,.limited-state{padding:32px 20px;color:#738a9a;font-size:12px;text-align:center}
        .limited-state{padding:34px 24px}
        .limited-state strong{display:block;margin-bottom:7px;color:#244e68;font-size:15px}
        .limited-state p{max-width:480px;margin:0 auto 18px;line-height:1.6}
        .archive-cards{display:none}
        .pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-top:1px solid #edf1f4;color:#748a9a;font-size:11px}
        .pagination .button{min-height:36px}
        .footer-note{margin-top:18px;color:#899aa6;font-size:10px;line-height:1.6;text-align:center}
        .notification-live-region{position:fixed;top:82px;right:16px;z-index:20;display:grid;gap:8px;width:min(360px,calc(100% - 24px));pointer-events:none}
        .notification-toast{padding:13px 15px;border:1px solid #dce6ec;border-radius:11px;background:#fff;box-shadow:0 12px 34px rgba(18,56,80,.16);pointer-events:auto}
        .notification-toast strong{color:#173b58;font-size:12px}
        .notification-toast p{margin:5px 0;color:#61798b;font-size:11px;line-height:1.5}
        .notification-toast a{font-size:10px;font-weight:700}
        .visually-hidden{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
        .auth-page{display:grid;min-height:calc(100vh - 68px);place-items:center;padding:36px 18px}
        .auth-card{width:min(100%,430px);padding:30px;border:1px solid #e0e9ef;border-radius:17px;background:#fff;box-shadow:0 16px 48px rgba(18,56,80,.09)}
        .auth-card h1{margin:8px 0;font-size:26px}
        .field{margin-top:18px}
        .field label{display:block;margin-bottom:7px;color:#405d71;font-size:12px;font-weight:700}
        .field input{width:100%;min-height:46px;padding:0 12px;border:1px solid #dce6ec;border-radius:9px;color:#173b58;font-size:16px}
        .field input:focus{border-color:#55a5cc;outline:3px solid rgba(8,127,195,.12)}
        .form-error{margin:6px 0 0;color:#a43b35;font-size:11px}
        .form-alert{margin-top:16px;padding:11px 13px;border-radius:9px;background:#fff1ef;color:#a43b35;font-size:12px}
        .auth-submit{width:100%;margin-top:22px}
        .auth-help{margin:15px 0 0;color:#7b8f9d;font-size:11px;line-height:1.6}
        @media(max-width:680px){
            .topbar{min-height:60px;padding:8px 12px;gap:9px;flex-wrap:wrap}
            .brand{min-width:0;flex:1}
            .brand-logo{width:68px;height:42px}
            .brand-title{font-size:14px}
            .user-label{display:none}
            .main-nav{order:3;width:100%;overflow-x:auto;padding-bottom:2px}
            .main-nav a{padding:8px 10px}
            .button{min-height:44px;padding:0 11px;font-size:11px}
            .page{padding:23px 13px 40px}
            .page-description{font-size:12px}
            .panel{margin-top:16px;border-radius:11px}
            .panel-heading{padding:15px}
            .archive-table-wrap{display:none}
            .archive-cards{display:grid;gap:9px;padding:12px}
            .archive-card{padding:13px;border:1px solid #e9eff3;border-radius:10px;background:#fff}
            .archive-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:8px}
            .archive-card-title{min-width:0;color:#1c405b;font-size:13px;font-weight:700;line-height:1.45}
            .archive-card-code{margin-top:4px;color:#7d909e;font-size:10px;overflow-wrap:anywhere}
            .archive-card-meta{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:13px;padding-top:10px;border-top:1px solid #edf1f4}
            .archive-card-meta span{display:block;color:#8a9ba6;font-size:9px}
            .archive-card-meta strong{display:block;margin-top:3px;color:#456478;font-size:10px;overflow-wrap:anywhere}
            .pagination{padding:12px;flex-wrap:wrap}
            .auth-card{padding:23px 19px;border-radius:14px}
            .auth-page{min-height:calc(100vh - 60px);padding:20px 12px}
        }
        .app-shell{min-height:100vh}
        .sidebar{position:fixed;inset:0 auto 0 0;z-index:30;display:flex;width:236px;flex-direction:column;padding:18px 13px 13px;border-right:1px solid #e1e9ee;background:#fff;box-shadow:8px 0 28px rgba(18,56,80,.035)}
        .sidebar .brand{min-width:0;gap:10px;padding:0 5px 17px;border-bottom:1px solid #edf1f4}
        .sidebar .brand-logo{width:69px;height:45px}
        .sidebar-user{display:grid;gap:4px;margin:14px 3px;padding:12px;border-radius:10px;background:#f4f8fa}
        .sidebar-user strong{overflow:hidden;color:#1c405b;font-size:12px;text-overflow:ellipsis;white-space:nowrap}
        .sidebar-user span{color:#8295a3;font-size:10px}
        .main-nav{display:grid;align-content:start;gap:3px;flex:1;overflow-y:auto;padding:2px 0 12px}
        .main-nav a{position:relative;display:flex;align-items:center;gap:10px;min-height:39px;padding:8px 10px;border-radius:8px;color:#49677c;font-size:11px;font-weight:650;white-space:normal}
        .main-nav a:hover,.main-nav a.active{background:#edf5ff;color:#075ec8}
        .main-nav a.active:before{position:absolute;left:-13px;top:8px;bottom:8px;width:3px;border-radius:0 3px 3px 0;background:#176bea;content:""}
        .nav-icon{width:16px;height:16px;flex:none;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.65}
        .sidebar-footer{padding-top:12px;border-top:1px solid #edf1f4}
        .sidebar-footer .button{width:100%}
        .app-main{min-height:100vh;margin-left:236px}
        .topbar{position:sticky;top:0;z-index:10;min-height:68px;padding:10px 26px;border-bottom:1px solid #e3ebf0;background:rgba(255,255,255,.96);backdrop-filter:blur(12px)}
        .topbar-page-title{overflow:hidden;color:#1c405b;font-size:13px;font-weight:800;text-overflow:ellipsis;white-space:nowrap}
        .global-search{display:flex;width:min(410px,38vw);min-width:210px;min-height:40px;align-items:center;gap:8px;padding:0 11px;border:1px solid #dbe5ec;border-radius:10px;background:#fff;color:#8395a3}
        .global-search:focus-within{border-color:#7aadd4;box-shadow:0 0 0 3px #176bea16}
        .global-search svg{width:16px;height:16px;flex:none;fill:none;stroke:currentColor;stroke-linecap:round;stroke-width:1.8}
        .global-search input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:#24465f;font-size:11px}
        .global-search input::placeholder{color:#90a0ad}
        .global-search kbd{flex:none;padding:3px 5px;border:1px solid #e3eaf0;border-radius:5px;color:#8c9ba7;font-size:8px}
        .topbar-notifications{padding:9px 11px;border-radius:8px;color:#49677c;font-size:11px;font-weight:700}
        .topbar-notifications:hover{background:#eff7fa;color:#087fc3}
        .topbar-actions{display:flex;align-items:center;gap:9px}
        .notification-link{position:relative;display:inline-flex;align-items:center;gap:7px;padding:9px 10px;border-radius:9px;color:#49677c;font-size:11px;font-weight:700}
        .notification-link:hover,.profile-menu[open]>summary{background:#eff7fa;color:#087fc3}
        .notification-count{display:inline-grid;min-width:19px;height:19px;place-items:center;padding:0 5px;border-radius:99px;background:#d94b56;color:#fff;font-size:9px;font-weight:800}
        .profile-menu{position:relative}
        .profile-trigger{display:flex;align-items:center;gap:8px;padding:5px 8px 5px 5px;border:1px solid transparent;border-radius:12px;color:#36566e;cursor:pointer;list-style:none}
        .profile-trigger::-webkit-details-marker{display:none}
        .profile-trigger:hover{border-color:#dce6ec;background:#f8fbfc}
        .profile-avatar{display:grid;width:36px;height:36px;flex:0 0 36px;place-items:center;overflow:hidden;border-radius:50%;background:#e7f3f9;color:#0876b3;font-size:13px;font-weight:800}
        .profile-avatar img{width:100%;height:100%;object-fit:cover}
        .profile-trigger-copy{display:grid;gap:2px;max-width:130px;text-align:left}
        .profile-trigger-copy strong{overflow:hidden;color:#29485e;font-size:11px;text-overflow:ellipsis;white-space:nowrap}
        .profile-trigger-copy small{color:#8295a3;font-size:9px}
        .profile-chevron{color:#8295a3;font-size:10px}
        .profile-dropdown{position:absolute;top:calc(100% + 9px);right:0;z-index:40;width:220px;padding:7px;border:1px solid #e1e9ee;border-radius:12px;background:#fff;box-shadow:0 15px 36px rgba(18,56,80,.16)}
        .profile-dropdown a,.profile-dropdown button{display:flex;width:100%;align-items:center;gap:9px;padding:10px;border:0;border-radius:8px;background:transparent;color:#49677c;font-size:11px;font-weight:700;text-align:left;text-decoration:none;cursor:pointer}
        .profile-dropdown a:hover,.profile-dropdown button:hover{background:#eff7fa;color:#087fc3}
        .profile-dropdown form{margin-top:4px;padding-top:4px;border-top:1px solid #edf1f4}
        .topbar-crumb{display:grid;gap:2px;min-width:0}
        .topbar-crumb small{color:#8295a3;font-size:9px}
        .sidebar-scrim{position:fixed;inset:0;z-index:25;border:0;background:rgba(16,38,54,.42);opacity:0;pointer-events:none;transition:opacity .2s ease}
        .sidebar-toggle-root{display:contents}
        .sidebar-toggle{display:none;align-items:center;justify-content:center;width:44px;height:44px;flex:0 0 44px;border:1px solid #dce6ec;border-radius:10px;background:#fff;color:#36566e;cursor:pointer}
        .sidebar-toggle:hover{border-color:#a9ccdc;background:#f8fbfc}
        .sidebar-toggle:focus-visible{outline:3px solid rgba(8,127,195,.28);outline-offset:2px}
        .sidebar-toggle-bars,.sidebar-toggle-bars:before,.sidebar-toggle-bars:after{display:block;width:18px;height:2px;border-radius:2px;background:currentColor}
        .sidebar-toggle-bars{position:relative}.sidebar-toggle-bars:before,.sidebar-toggle-bars:after{position:absolute;left:0;content:""}.sidebar-toggle-bars:before{top:-6px}.sidebar-toggle-bars:after{top:6px}
        .guest-topbar{min-height:68px;padding:10px max(20px,calc((100vw - 1240px)/2))}
        @media(max-width:900px){
            .sidebar{transform:translateX(-105%);transition:transform .2s ease}
            .app-main{margin-left:0}
            body.sidebar-open{overflow:hidden}
            body.sidebar-open .sidebar{transform:translateX(0)}
            body.sidebar-open .sidebar-scrim{opacity:1;pointer-events:auto}
            .sidebar-toggle{display:inline-flex}
            .topbar{gap:12px;padding:8px 16px}
            .global-search{flex:1;width:auto;min-width:150px}
            .topbar-spacer{flex:1}
            .topbar-notifications{white-space:nowrap}
            .notification-live-region{top:70px}
        }
        @media(max-width:560px){
            .topbar{min-height:58px;padding:7px 11px;gap:8px;flex-wrap:wrap}
            .topbar-page-title{font-size:12px}
            .global-search{order:5;flex:0 0 100%;width:100%;min-width:0;min-height:40px}
            .notification-link{padding:8px}
            .notification-link-label,.profile-trigger-copy,.profile-chevron{display:none}
            .topbar-actions{gap:4px}
            .topbar .user-label{display:none}
            .sidebar{width:min(290px,88vw);padding:16px 12px 12px}
            .sidebar .brand{order:0;flex:0 0 auto}
            .sidebar .main-nav{order:0;width:100%;overflow-x:visible;padding-bottom:12px}
            .sidebar .main-nav a{min-height:42px;font-size:12px}
            .sidebar .sidebar-footer{order:0;flex:0 0 auto}
            .guest-topbar{min-height:60px;padding:8px 12px}
            .page{padding:20px 13px 38px}
            .notification-live-region{top:64px;right:12px}
        }
        @media print{
            .sidebar,.sidebar-scrim,.topbar,.notification-live-region{display:none!important}
            .app-main{margin:0}
        }
        @media(prefers-reduced-motion:reduce){*,*:before,*:after{scroll-behavior:auto!important;transition:none!important}}
    </style>
</head>
<body @auth data-notification-feed="{{ route('notifications.feed') }}" @endauth>
    @if (request()->routeIs('login'))
    <button class="theme-toggle theme-login-toggle" type="button" data-theme-toggle aria-pressed="false">Gunakan tema gelap</button>
    @endif
    <svg xmlns="http://www.w3.org/2000/svg" class="visually-hidden" aria-hidden="true">
        <symbol id="nav-dashboard" viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="4" rx="1.5"/><rect x="13.5" y="10.5" width="7" height="10" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/></symbol>
        <symbol id="nav-scan" viewBox="0 0 24 24"><path d="M4 8V4h4m8 0h4v4M4 16v4h4m8 0h4v-4M8 8h2v2H8zm6 0h2v2h-2zm-6 6h2v2H8zm6 0h3v3h-3z"/></symbol>
        <symbol id="nav-label" viewBox="0 0 24 24"><path d="M4 7h16v13H4zM3 4h18v3H3zm4 7h3v3H7zm6 0h4m-4 3h4"/></symbol>
        <symbol id="nav-archive" viewBox="0 0 24 24"><path d="M6 3.5h8l4 4v13H6zM14 3.5v4h4M9 12h6m-6 3.5h6"/></symbol>
        <symbol id="nav-loan" viewBox="0 0 24 24"><path d="M4 7h16v13H4zM3 4h18v3H3zm9 6v7m-3-3 3 3 3-3"/></symbol>
        <symbol id="nav-retention" viewBox="0 0 24 24"><path d="M5 5h14v15H5zM8 3v4m8-4v4M8 11h8m-8 4h5"/></symbol>
        <symbol id="nav-approval" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6M4 4h10"/></symbol>
        <symbol id="nav-customer" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M3.5 20c.5-3.7 2.3-5.5 5.5-5.5s5 1.8 5.5 5.5m3-11h3m-1.5-1.5v3"/></symbol>
        <symbol id="nav-report" viewBox="0 0 24 24"><path d="M5 20V11m7 9V4m7 16v-7M3 20h18"/></symbol>
        <symbol id="nav-import" viewBox="0 0 24 24"><path d="M12 15V4m-4 4 4-4 4 4M5 14v6h14v-6"/></symbol>
        <symbol id="nav-category" viewBox="0 0 24 24"><path d="M4 5h16v4H4zm0 6h16v4H4zm0 6h16v3H4z"/></symbol>
        <symbol id="nav-location" viewBox="0 0 24 24"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.2"/></symbol>
        <symbol id="nav-history" viewBox="0 0 24 24"><path d="M4 8V4m0 4h4M4.8 8A8 8 0 1 1 4 13m8-5v5l3 2"/></symbol>
        <symbol id="nav-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3.5 20c.4-3.6 2.3-5.4 5.5-5.4s5.1 1.8 5.5 5.4M16 5.5a3 3 0 0 1 0 5.7m1 3.4c2.1.5 3.3 2.3 3.5 5.4"/></symbol>
        <symbol id="nav-duplicate" viewBox="0 0 24 24"><rect x="4" y="4" width="11" height="14" rx="2"/><path d="M9 20h9a2 2 0 0 0 2-2V8M7 8h5m-5 4h5"/></symbol>
        <symbol id="nav-bell" viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12h4"/></symbol>
        <symbol id="nav-search" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.3"/><path d="m16 16 4 4"/></symbol>
    </svg>
    @auth
        <div class="app-shell">
            <aside class="sidebar" id="main-sidebar" aria-label="Panel navigasi">
                <a class="brand" href="{{ route('dashboard') }}">
                    <img class="brand-logo" src="{{ asset('images/bakti-logo.png') }}" alt="Logo BAKTI Komdigi">
                    <span><span class="brand-title">Sistem Arsip</span><span class="brand-subtitle">BAKTI Komdigi · Gudang Utama</span></span>
                </a>
                <nav class="main-nav" aria-label="Navigasi utama">
                @if (auth()->user()->role === 'Pegawai')
                    <a href="{{ route('pegawai.dashboard') }}" @class(['active' => request()->routeIs('pegawai.dashboard')])><svg class="nav-icon"><use href="#nav-dashboard"/></svg>Dashboard</a>
                    <a href="{{ route('archives.index') }}" @class(['active' => request()->routeIs('archives.*')])><svg class="nav-icon"><use href="#nav-archive"/></svg>Data Arsip</a>
                    <a href="{{ route('qr.scanner') }}" @class(['active' => request()->routeIs('qr.scanner')])><svg class="nav-icon"><use href="#nav-scan"/></svg>Scan QR</a>
                    <a href="{{ route('employee-loans.index') }}" @class(['active' => request()->routeIs('employee-loans.*')])><svg class="nav-icon"><use href="#nav-loan"/></svg>Peminjaman Tanpa QR</a>
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::LOANS_REQUEST))
                        <a href="{{ route('loan-requests.index') }}" @class(['active' => request()->routeIs('loan-requests.*')])><svg class="nav-icon"><use href="#nav-approval"/></svg>Permintaan Peminjaman</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::DISTRIBUTIONS_REQUEST))
                        <a href="{{ route('distributions.index') }}" @class(['active' => request()->routeIs('distributions.*')])><svg class="nav-icon"><use href="#nav-import"/></svg>Distribusi Arsip</a>
                    @endif
                    <a href="{{ route('history.index') }}" @class(['active' => request()->routeIs('history.*')])><svg class="nav-icon"><use href="#nav-history"/></svg>Riwayat</a>
                    <a href="{{ route('notifications.index') }}" @class(['active' => request()->routeIs('notifications.*')])><svg class="nav-icon"><use href="#nav-bell"/></svg>Notifikasi</a>
                    <a href="{{ route('account.show') }}" @class(['active' => request()->routeIs('account.*')])><svg class="nav-icon"><use href="#nav-users"/></svg>Profil</a>
                @else
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::DASHBOARD_VIEW))
                        <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])><svg class="nav-icon"><use href="#nav-dashboard"/></svg>Dashboard</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::QR_SCAN))
                        <a href="{{ route('qr.scanner') }}" @class(['active' => request()->routeIs('qr.scanner')])><svg class="nav-icon"><use href="#nav-scan"/></svg>Scan QR</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::QR_MANAGE))
                        <a href="{{ route('qr.index') }}" @class(['active' => request()->routeIs('qr.index', 'qr.archive-label', 'qr.box-label', 'boxes.*')])><svg class="nav-icon"><use href="#nav-label"/></svg>Cetak Label QR</a>
                    @endif
                    @if (\App\Support\ArchiveAccess::canAccessListing(auth()->user()))
                        <a href="{{ route('archives.index') }}" @class(['active' => request()->routeIs('archives.*')])><svg class="nav-icon"><use href="#nav-archive"/></svg>Data Arsip</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::LOANS_MANAGE))
                        <a href="{{ route('loans.index') }}" @class(['active' => request()->routeIs('loans.*')])><svg class="nav-icon"><use href="#nav-loan"/></svg>Peminjaman</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::DISTRIBUTIONS_REQUEST)
                        || \App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::DISTRIBUTIONS_APPROVE)
                        || \App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::DISTRIBUTIONS_DISPATCH)
                        || \App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::DISTRIBUTIONS_RECEIVE))
                        <a href="{{ route('distributions.index') }}" @class(['active' => request()->routeIs('distributions.*')])><svg class="nav-icon"><use href="#nav-import"/></svg>Distribusi Arsip</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::RETENTION_VIEW))
                        <a href="{{ route('retention.index') }}" @class(['active' => request()->routeIs('retention.*')])><svg class="nav-icon"><use href="#nav-retention"/></svg>Retensi</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::LOANS_APPROVE))
                        <a href="{{ route('loan-approvals.index') }}" @class(['active' => request()->routeIs('loan-approvals.*')])><svg class="nav-icon"><use href="#nav-approval"/></svg>Persetujuan</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::REPORTS_VIEW))
                        <a href="{{ route('reports.index') }}" @class(['active' => request()->routeIs('reports.*')])><svg class="nav-icon"><use href="#nav-report"/></svg>Laporan</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::IMPORTS_MANAGE))
                        <a href="{{ route('imports.index') }}" @class(['active' => request()->routeIs('imports.*')])><svg class="nav-icon"><use href="#nav-import"/></svg>Impor / Ekspor</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::CATEGORIES_MANAGE))
                        <a href="{{ route('categories.index') }}" @class(['active' => request()->routeIs('categories.*')])><svg class="nav-icon"><use href="#nav-category"/></svg>Kategori</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::LOCATIONS_MANAGE))
                        <a href="{{ route('locations.index') }}" @class(['active' => request()->routeIs('locations.*')])><svg class="nav-icon"><use href="#nav-location"/></svg>Lokasi</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::HISTORY_VIEW))
                        <a href="{{ route('history.index') }}" @class(['active' => request()->routeIs('history.*')])><svg class="nav-icon"><use href="#nav-history"/></svg>Riwayat</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::USERS_MANAGE))
                        <a href="{{ route('users.index') }}" @class(['active' => request()->routeIs('users.*')])><svg class="nav-icon"><use href="#nav-users"/></svg>Pengguna</a>
                    @endif
                    @if (\App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::DUPLICATES_REVIEW))
                        <a href="{{ route('duplicates.index') }}" @class(['active' => request()->routeIs('duplicates.*')])><svg class="nav-icon"><use href="#nav-duplicate"/></svg>Verifikasi Duplikasi</a>
                    @endif
                @endif
                </nav>
            </aside>
            <div class="app-main">
                <header class="topbar">
                    <div class="sidebar-toggle-root" data-sidebar-controls></div>
                    <div class="topbar-crumb"><small>{{ \App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::CUSTOMER_PORTAL) ? 'Portal layanan' : 'Arsip BAKTI' }}</small><strong class="topbar-page-title">@yield('title', 'Arsip BAKTI')</strong></div>
                    @if (\App\Support\ArchiveAccess::canAccessListing(auth()->user()))
                        <form class="global-search" method="get" action="{{ \App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::CUSTOMER_PORTAL) ? route('customer.archives.index') : route('archives.index') }}" role="search">
                            <label class="visually-hidden" for="global-archive-search">Cari arsip</label>
                            <svg aria-hidden="true"><use href="#nav-search"/></svg>
                            <input id="global-archive-search" type="search" name="q" value="{{ request()->query('q') }}" placeholder="{{ \App\Support\RolePermissions::allows(auth()->user(), \App\Support\RolePermissions::CUSTOMER_PORTAL) ? 'Cari arsip publik…' : 'Cari arsip, nomor, judul, kategori, lokasi…' }}" autocomplete="off">
                            <kbd aria-hidden="true">Cari</kbd>
                        </form>
                    @endif
                    <span class="topbar-spacer"></span>
                    @php($unreadNotifications = \Illuminate\Support\Facades\DB::table('notifications')->where('user_id', auth()->id())->whereNull('read_at')->count())
                        <div class="topbar-actions">
                        <a class="notification-link" href="{{ route('notifications.index') }}" aria-label="Notifikasi{{ $unreadNotifications ? ', '.$unreadNotifications.' belum dibaca' : '' }}">
                            <svg class="nav-icon" aria-hidden="true"><use href="#nav-bell"/></svg><span class="notification-link-label">Notifikasi</span>
                            @if ($unreadNotifications > 0)<span class="notification-count">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>@endif
                        </a>
                        <details class="profile-menu">
                            <summary class="profile-trigger">
                                <span class="profile-avatar">
                                    <span data-avatar-fallback>{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->nama, 0, 1)) }}</span>
                                    @if (auth()->user()->profile_image)
                                        <img src="{{ route('account.photo', ['filename' => basename(auth()->user()->profile_image)]) }}" alt="" data-profile-photo onerror="this.hidden=true">
                                    @endif
                                </span>
                                <span class="profile-trigger-copy"><strong>{{ auth()->user()->nama }}</strong><small>{{ auth()->user()->role }}</small></span>
                                <span class="profile-chevron" aria-hidden="true">▾</span>
                            </summary>
                            <div class="profile-dropdown">
                                <button type="button" data-theme-toggle aria-pressed="false">Gunakan tema gelap</button>
                                <a href="{{ route('account.show') }}">Profil &amp; pengaturan akun</a>
                                <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Keluar dari aplikasi</button></form>
                            </div>
                        </details>
                    </div>
                </header>
                <div class="notification-live-region" data-notification-toasts aria-live="polite" aria-relevant="additions"></div>
                <span class="visually-hidden" data-notification-status aria-live="polite"></span>
                @yield('content')
            </div>
        </div>
    @else
        @unless (request()->routeIs('login'))
            <header class="topbar guest-topbar">
                <a class="brand" href="{{ route('login') }}">
                    <img class="brand-logo" src="{{ asset('images/bakti-logo.png') }}" alt="Logo BAKTI Komdigi">
                    <span><span class="brand-title">Sistem Arsip</span><span class="brand-subtitle">BAKTI Komdigi · Gudang Utama</span></span>
                </a>
                <button class="theme-toggle" type="button" data-theme-toggle aria-pressed="false">Gunakan tema gelap</button>
            </header>
        @endunless
        @yield('content')
    @endauth
</body>
</html>
