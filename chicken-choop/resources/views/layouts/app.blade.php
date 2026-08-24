<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Smart Coop IoT Dashboard')</title>
    <meta name="description" content="Smart Coop IoT — Sistem monitoring dan kontrol kandang ayam otomatis berbasis Internet of Things.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>

    <style>
        /* ══════════════════════════════════════════════
           DESIGN SYSTEM — Soft Light Theme
           ══════════════════════════════════════════════ */

        :root {
            --bg-body: #eef2f7;
            --bg-card: #ffffff;
            --bg-card-alt: #f8fafc;
            --border: #e2e7ed;
            --border-light: #f0f3f7;
            --shadow-sm: 0 1px 2px rgba(0,0,0,.03), 0 1px 3px rgba(0,0,0,.04);
            --shadow-md: 0 4px 16px rgba(0,0,0,.05);
            --shadow-lg: 0 8px 30px rgba(0,0,0,.07);
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --accent: #059669;
            --accent-hover: #047857;
            --accent-bg: #ecfdf5;
            --accent-border: #a7f3d0;
            --radius: .875rem;
            --radius-sm: .625rem;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Card ── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            transition: box-shadow .25s ease, transform .25s ease;
        }
        .card:hover {
            box-shadow: var(--shadow-md);
        }

        /* ── Sidebar ── */
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .55rem .875rem;
            border-radius: var(--radius-sm);
            font-size: .8125rem;
            font-weight: 500;
            color: var(--text-secondary);
            transition: all .2s ease;
            border-left: 3px solid transparent;
            margin-left: -3px;
        }
        .sidebar-link:hover {
            background: var(--bg-card-alt);
            color: var(--text-primary);
        }
        .sidebar-link.active {
            background: var(--accent-bg);
            color: var(--accent);
            border-left-color: var(--accent);
            font-weight: 600;
        }
        .sidebar-link.active i { color: var(--accent); }

        #sidebar { transition: transform .3s cubic-bezier(.4,0,.2,1); }
        #sidebar.collapsed { transform: translateX(-100%); }
        #overlay { transition: opacity .25s ease; }

        /* ── Notification ── */
        .notif-dot { width:7px; height:7px; border-radius:50%; background:#ef4444; position:absolute; top:3px; right:3px; border:2px solid #fff; }
        .notif-panel { display:none; position:absolute; right:0; top:calc(100% + .5rem); width:22rem; background:#fff; border:1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-lg); z-index:60; max-height:24rem; overflow-y:auto; }
        .notif-panel.open { display:block; }

        /* ── Data Table ── */
        .data-table { width:100%; text-align:left; font-size:.8125rem; color:var(--text-secondary); }
        .data-table thead { font-size:.6875rem; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted); background:var(--bg-card-alt); }
        .data-table th { padding:.625rem .875rem; font-weight:600; white-space:nowrap; }
        .data-table th:first-child { border-radius: var(--radius-sm) 0 0 var(--radius-sm); }
        .data-table th:last-child { border-radius: 0 var(--radius-sm) var(--radius-sm) 0; }
        .data-table td { padding:.625rem .875rem; }
        .data-table tbody tr { border-bottom:1px solid var(--border-light); transition:background .15s ease; }
        .data-table tbody tr:hover { background: var(--bg-card-alt); }
        .data-table tbody tr:last-child { border-bottom:none; }

        /* ── Badge ── */
        .badge { display:inline-flex; align-items:center; gap:.25rem; padding:.175rem .5rem; border-radius:9999px; font-size:.6875rem; font-weight:600; white-space:nowrap; }
        .badge-success { background:#ecfdf5; color:#059669; }
        .badge-danger  { background:#fef2f2; color:#dc2626; }
        .badge-warning { background:#fffbeb; color:#d97706; }
        .badge-info    { background:#eff6ff; color:#2563eb; }
        .badge-muted   { background:#f1f5f9; color:#94a3b8; }
        .badge-purple  { background:#faf5ff; color:#7c3aed; }

        /* ── Buttons ── */
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:.375rem; padding:.5rem .875rem; border-radius:var(--radius-sm); font-size:.75rem; font-weight:600; transition:all .2s ease; cursor:pointer; border:1px solid transparent; }
        .btn-primary { background:linear-gradient(135deg,#059669,#10b981); color:#fff; box-shadow:0 2px 8px rgba(5,150,105,.18); }
        .btn-primary:hover { box-shadow:0 4px 16px rgba(5,150,105,.28); transform:translateY(-1px); }
        .btn-secondary { background:#fff; color:var(--text-secondary); border-color:var(--border); }
        .btn-secondary:hover { background:var(--bg-card-alt); border-color:#cbd5e1; }
        .btn-accent { background:var(--accent-bg); color:var(--accent); border-color:var(--accent-border); }
        .btn-accent:hover { background:#d1fae5; }
        .btn-danger { background:#fef2f2; color:#dc2626; border-color:#fecaca; }
        .btn-danger:hover { background:#fee2e2; }
        .btn-sm { padding:.375rem .625rem; font-size:.6875rem; }
        .btn-xs { padding:.25rem .5rem; font-size:.625rem; border-radius:.375rem; }

        /* ── Form ── */
        .form-input, .form-select {
            width:100%; padding:.5rem .75rem; border:1px solid var(--border); border-radius:var(--radius-sm);
            font-size:.8125rem; color:var(--text-primary); background:#fff; transition:border-color .2s, box-shadow .2s;
        }
        .form-input:focus, .form-select:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(5,150,105,.08); }

        /* ── Metric ── */
        .metric-value { font-size:1.875rem; font-weight:900; color:var(--text-primary); line-height:1.1; }
        .metric-unit  { font-size:.875rem; font-weight:600; color:var(--text-muted); margin-left:.125rem; }
        .metric-label { font-size:.6875rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted); }

        /* ── Section ── */
        .section-title { font-size:.8125rem; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:.5rem; }
        .section-title i { color:var(--accent); font-size:.75rem; }

        /* ── Icon Box ── */
        .icon-box { width:2.25rem; height:2.25rem; border-radius:.5rem; display:flex; align-items:center; justify-content:center; font-size:.875rem; flex-shrink:0; }

        /* ── Thermometer Visual ── */
        .thermo-wrap { position:relative; width:22px; height:120px; background:var(--border-light); border-radius:999px; margin:0 auto; box-shadow:inset 0 2px 4px rgba(0,0,0,.04); }
        .thermo-fluid { position:absolute; bottom:0; left:0; width:100%; border-radius:999px; transition:height 1s cubic-bezier(0.4, 0, 0.2, 1); }
        .thermo-bulb { width:34px; height:34px; border-radius:50%; position:absolute; bottom:-16px; left:-6px; z-index:2; transition:background-color 1s; box-shadow:0 3px 6px rgba(0,0,0,.1); }

        /* ── Animations ── */
        @keyframes fadeInUp { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
        .animate-in { animation: fadeInUp .35s ease forwards; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width:5px; height:5px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:3px; }
        ::-webkit-scrollbar-thumb:hover { background:#9ca3af; }

        /* ── Pulse dot ── */
        .pulse-dot { width:6px; height:6px; border-radius:50%; animation: pulse-glow 2s ease-in-out infinite; }
        @keyframes pulse-glow { 0%,100% { opacity:1; } 50% { opacity:.4; } }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen">

    <!-- MOBILE OVERLAY -->
    <div id="overlay" class="fixed inset-0 bg-black/20 backdrop-blur-sm z-30 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="fixed top-0 left-0 z-40 h-full w-[15.5rem] bg-white border-r border-slate-200/80 flex flex-col px-4 py-5 md:translate-x-0 -translate-x-full">
        @include('layouts.sidebar')
    </aside>

    <!-- MAIN WRAPPER -->
    <div id="main-wrapper" class="transition-all duration-300 md:ml-[15.5rem]">

        <!-- TOP HEADER -->
        <header class="sticky top-0 z-20 bg-white/80 backdrop-blur-lg border-b border-slate-200/60 px-4 sm:px-6 py-2.5 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-600 transition" aria-label="Toggle sidebar">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <div class="hidden sm:flex items-center gap-2">
                    <span class="text-base">@yield('page_icon', '📊')</span>
                    <h2 class="text-sm font-semibold text-slate-700">@yield('page_title', 'Dashboard')</h2>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- MQTT Status -->
                <div id="mqtt-pill" class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-semibold border border-emerald-100">
                    <span class="pulse-dot bg-emerald-500"></span>
                    <span id="mqtt-status-text">MQTT</span>
                </div>

                <!-- Clock -->
                <div class="px-2.5 py-1 rounded-full bg-slate-50 text-[10px] font-mono font-semibold text-slate-400 border border-slate-100" id="global-clock-display">--:--:--</div>

                <!-- Notification Bell -->
                <div class="relative">
                    <button onclick="toggleNotifPanel()" class="w-8 h-8 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-600 transition relative">
                        <i class="fa-solid fa-bell text-sm"></i>
                        <span id="notif-dot" class="notif-dot hidden"></span>
                    </button>
                    <div id="notif-panel" class="notif-panel">
                        <div class="p-3 border-b border-slate-100 flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-600">Notifikasi</span>
                            <span id="notif-count" class="text-[10px] font-bold bg-red-50 text-red-500 px-1.5 py-0.5 rounded-full hidden">0</span>
                        </div>
                        <div id="notif-list" class="divide-y divide-slate-50">
                            <div class="p-4 text-center text-xs text-slate-400">Tidak ada notifikasi baru.</div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT -->
        <main class="p-4 sm:p-6 max-w-[1400px] mx-auto">
            @if(session('success'))
                <div class="mb-4 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs flex items-center justify-between shadow-sm animate-in">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-sm text-emerald-600"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 text-xs">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs flex items-center justify-between shadow-sm animate-in">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-sm text-red-600"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700 text-xs">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- GLOBAL JS -->
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Chart.js Gauge Needle Plugin
        const gaugeNeedle = {
            id: 'gaugeNeedle',
            afterDatasetDraw(chart, args, options) {
                const { ctx, config, data, chartArea: { top, bottom, left, right, width, height } } = chart;
                if (config.type !== 'doughnut') return;
                
                ctx.save();
                const dataTotal = data.datasets[0].data.reduce((a, b) => a + b, 0);
                if (dataTotal === 0) return;
                
                const value = data.datasets[0].data[0];
                const meta = chart.getDatasetMeta(0);
                const arc = meta.data[0];
                const cx = arc.x;
                const cy = arc.y;
                const innerRadius = arc.innerRadius;

                const angle = Math.PI + (value / dataTotal) * Math.PI;

                ctx.translate(cx, cy);
                ctx.rotate(angle);

                // Draw Needle (pointed at the chart, base at the center)
                const needleLength = innerRadius - 8;
                
                ctx.beginPath();
                ctx.moveTo(0, -4);
                ctx.lineTo(needleLength, 0);
                ctx.lineTo(0, 4);
                ctx.fillStyle = '#64748b'; // slate-500
                ctx.fill();

                // Draw Center Dot
                ctx.beginPath();
                ctx.arc(0, 0, 8, 0, Math.PI * 2);
                ctx.fillStyle = '#334155'; // slate-700
                ctx.fill();
                
                // Draw inner dot for reflection
                ctx.beginPath();
                ctx.arc(0, 0, 3, 0, Math.PI * 2);
                ctx.fillStyle = '#94a3b8'; 
                ctx.fill();

                ctx.restore();
            }
        };

        /* ── Sidebar Toggle ── */
        let sidebarOpen = window.innerWidth >= 768;
        function toggleSidebar() {
            const sb = document.getElementById('sidebar');
            const ol = document.getElementById('overlay');
            const mw = document.getElementById('main-wrapper');
            sidebarOpen = !sidebarOpen;

            if (window.innerWidth < 768) {
                sb.classList.toggle('-translate-x-full', !sidebarOpen);
                ol.classList.toggle('hidden', !sidebarOpen);
            } else {
                sb.classList.toggle('collapsed', !sidebarOpen);
                mw.style.marginLeft = sidebarOpen ? '15.5rem' : '0';
            }
        }
        window.addEventListener('resize', () => {
            const sb = document.getElementById('sidebar');
            const ol = document.getElementById('overlay');
            const mw = document.getElementById('main-wrapper');
            if (window.innerWidth >= 768) {
                ol.classList.add('hidden');
                sb.classList.remove('-translate-x-full');
                if (!sb.classList.contains('collapsed')) { mw.style.marginLeft = '15.5rem'; }
            } else {
                mw.style.marginLeft = '0';
                if (!sidebarOpen) sb.classList.add('-translate-x-full');
            }
        });

        /* ── Clock ── */
        function updateClock() {
            const now = new Date();
            document.getElementById('global-clock-display').innerText = now.toLocaleTimeString('id-ID', {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }) + ' WIB';
        }
        setInterval(updateClock, 1000); updateClock();

        /* ── Notification Panel ── */
        function toggleNotifPanel() {
            document.getElementById('notif-panel').classList.toggle('open');
        }
        document.addEventListener('click', e => {
            const panel = document.getElementById('notif-panel');
            if (panel && panel.classList.contains('open') && !e.target.closest('.relative')) panel.classList.remove('open');
        });

        function pushNotification(title, desc, type) {
            const list = document.getElementById('notif-list');
            const dot = document.getElementById('notif-dot');
            const cnt = document.getElementById('notif-count');
            const colors = { emergency: 'bg-red-50 border-l-red-500 text-red-700', warning: 'bg-amber-50 border-l-amber-500 text-amber-700', info: 'bg-blue-50 border-l-blue-500 text-blue-700' };
            const c = colors[type] || colors.info;
            const item = document.createElement('div');
            item.className = `p-3 border-l-4 ${c} text-xs`;
            const timeStr = new Date().toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
            item.innerHTML = `<div class="font-semibold">${title}</div><div class="mt-0.5 opacity-75">${desc}</div><div class="mt-1 opacity-40 text-[10px]">${timeStr}</div>`;
            if (list.children.length === 1 && list.children[0].textContent.includes('Tidak ada')) list.innerHTML = '';
            list.prepend(item);
            dot.classList.remove('hidden');
            const n = list.children.length;
            cnt.classList.remove('hidden'); cnt.innerText = n;
        }

        /* ── MQTT ── */
        const mqttClient = mqtt.connect('wss://broker.hivemq.com:8884/mqtt', {
            clientId: 'web_sc_' + Math.random().toString(16).substring(2,8),
            keepalive: 60, reconnectPeriod: 2000
        });
        mqttClient.on('connect', () => {
            document.getElementById('mqtt-status-text').innerText = 'MQTT';
            const pill = document.getElementById('mqtt-pill');
            pill.className = 'hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-semibold border border-emerald-100';
            mqttClient.subscribe('chickencoop/#');
        });
        mqttClient.on('offline', () => {
            document.getElementById('mqtt-status-text').innerText = 'Offline';
            const pill = document.getElementById('mqtt-pill');
            pill.className = 'hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 text-amber-600 text-[10px] font-semibold border border-amber-100';
        });
        mqttClient.on('message', () => { if (typeof fetchRealtimeState === 'function') fetchRealtimeState(); });

        /* ── Global Actuator Control ── */
        async function controlActuator(device, action) {
            try {
                const res = await fetch('/api/actuator/control', {
                    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
                    body: JSON.stringify({device, action})
                });
                const r = await res.json();
                if (r.success && typeof fetchRealtimeState === 'function') fetchRealtimeState();
            } catch(e) { console.error(e); }
        }
        async function toggleGlobalControlMode() {
            const el = document.getElementById('sidebar-control-mode-text');
            const next = (el ? el.innerText.toLowerCase() : 'auto') === 'auto' ? 'manual' : 'auto';
            await controlActuator('mode', next);
            location.reload();
        }
    </script>
    @stack('scripts')
</body>
</html>
