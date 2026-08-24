@extends('layouts.app')

@section('title', 'Overview — Smart Coop IoT')
@section('page_icon', '📊')
@section('page_title', 'Overview Monitoring')

@section('content')
<div class="space-y-5 animate-in">

    <!-- METRICS GRID (5 CARDS) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">

        <!-- Suhu Kandang -->
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="metric-label">Suhu Kandang</span>
                <div class="icon-box bg-amber-50 text-amber-500">
                    <i class="fa-solid fa-temperature-half"></i>
                </div>
            </div>
            <div class="flex items-center gap-6 justify-center mt-3 mb-2">
                <div class="thermo-wrap">
                    <div id="ov-thermo-fluid" class="thermo-fluid {{ $latestTemp->status === 'Normal' ? 'bg-gradient-to-t from-emerald-500 to-emerald-400' : 'bg-gradient-to-t from-red-500 to-red-400' }}" style="height: {{ min(max((($latestTemp->temperature)/50)*100,10),100) }}%;"></div>
                    <div id="ov-thermo-bulb" class="thermo-bulb {{ $latestTemp->status === 'Normal' ? 'bg-emerald-500' : 'bg-red-500' }}"></div>
                </div>
                <div>
                    <div class="flex items-baseline">
                        <span id="ov-temp" class="metric-value text-3xl">{{ number_format($latestTemp->temperature, 1) }}</span>
                        <span class="metric-unit text-sm">°C</span>
                    </div>
                    <div class="mt-1">
                        <span id="ov-temp-badge" class="badge {{ $latestTemp->status === 'Normal' ? 'badge-success' : 'badge-danger' }} text-[9px] px-1.5 py-0.5">
                            {{ $latestTemp->status }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lampu Pemanas -->
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="metric-label">Lampu Pemanas</span>
                <div class="icon-box bg-yellow-50 text-yellow-600">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
            </div>
            <div class="flex items-baseline">
                <span id="ov-lamp" class="text-xl font-extrabold {{ $latestTemp->lamp_status === 'ON' ? 'text-yellow-600' : 'text-slate-300' }}">
                    {{ $latestTemp->lamp_status }}
                </span>
            </div>
            <div class="flex gap-1.5 mt-3">
                <button onclick="controlActuator('lamp','ON')" class="btn btn-xs btn-accent flex-1">ON</button>
                <button onclick="controlActuator('lamp','OFF')" class="btn btn-xs btn-secondary flex-1">OFF</button>
            </div>
        </div>

        <!-- Kipas Pendingin -->
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="metric-label">Kipas Pendingin</span>
                <div class="icon-box bg-cyan-50 text-cyan-600">
                    <i class="fa-solid fa-fan"></i>
                </div>
            </div>
            <div class="flex items-baseline">
                <span id="ov-fan" class="text-xl font-extrabold {{ $latestTemp->fan_status === 'ON' ? 'text-cyan-600' : 'text-slate-300' }}">
                    {{ $latestTemp->fan_status }}
                </span>
            </div>
            <div class="flex gap-1.5 mt-3">
                <button onclick="controlActuator('fan','ON')" class="btn btn-xs btn-accent flex-1">ON</button>
                <button onclick="controlActuator('fan','OFF')" class="btn btn-xs btn-secondary flex-1">OFF</button>
            </div>
        </div>

        <!-- Level Air -->
        <div class="card p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="metric-label">Level Air</span>
                <div class="icon-box bg-blue-50 text-blue-500">
                    <i class="fa-solid fa-droplet"></i>
                </div>
            </div>
            <div class="w-full max-w-[120px] mx-auto mt-2 mb-1">
                <canvas id="ovWaterGauge"></canvas>
                <div class="flex flex-col items-center mt-1 pointer-events-none">
                    <div class="flex items-baseline">
                        <span id="ov-water" class="metric-value text-xl">{{ number_format($latestWater->water_level, 0) }}</span>
                        <span class="metric-unit text-xs">%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pompa Air -->
        <div class="card p-4 col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between mb-2">
                <span class="metric-label">Pompa Air</span>
                <div class="icon-box bg-indigo-50 text-indigo-500">
                    <i class="fa-solid fa-faucet-drip"></i>
                </div>
            </div>
            <div class="flex items-baseline">
                <span id="ov-pump" class="text-xl font-extrabold {{ $latestWater->pump_status === 'ON' ? 'text-indigo-600' : 'text-slate-300' }}">
                    {{ $latestWater->pump_status }}
                </span>
            </div>
            <p id="ov-pump-desc" class="text-[10px] text-slate-400 mt-2 truncate">{{ $latestWater->state_desc }}</p>
        </div>

    </div>

    <!-- ROW 2: PAKAN SUMMARY & QUICK FEED -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
        <!-- Jadwal Berikutnya -->
        <div class="card p-4 flex items-center gap-3.5">
            <div class="icon-box bg-purple-50 text-purple-600 w-10 h-10 text-base">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="min-w-0">
                <p class="metric-label">Jadwal Pakan Berikutnya</p>
                <p id="ov-next-time" class="text-lg font-black text-slate-800 font-mono">{{ $nextSchedule ? $nextSchedule['time'] : '--:--' }}</p>
                <p id="ov-next-label" class="text-[11px] text-slate-400 truncate">{{ $nextSchedule ? $nextSchedule['label'] : 'Belum ada jadwal' }}</p>
            </div>
        </div>

        <!-- Pakan Terakhir -->
        <div class="card p-4 flex items-center gap-3.5">
            <div class="icon-box bg-orange-50 text-orange-500 w-10 h-10 text-base">
                <i class="fa-solid fa-bowl-rice"></i>
            </div>
            <div class="min-w-0">
                <p class="metric-label">Pemberian Terakhir</p>
                <p id="ov-last-feed" class="text-sm font-bold text-slate-700 truncate">{{ $lastFeeding ? $lastFeeding->fed_at->format('d/m/Y H:i') : '-' }}</p>
                <p id="ov-last-feed-src" class="text-[11px] text-slate-400 truncate">{{ $lastFeeding ? $lastFeeding->source . ' — ' . $lastFeeding->portion_grams . 'g' : '-' }}</p>
            </div>
        </div>

        <!-- Quick Manual Feed -->
        <div class="card p-4 flex items-center gap-3.5">
            <div class="icon-box bg-emerald-50 text-emerald-600 w-10 h-10 text-base">
                <i class="fa-solid fa-wheat-awn"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="metric-label">Quick Feeding</p>
                <button onclick="triggerManualFeed()" id="btn-quick-feed" class="mt-1 w-full btn btn-primary btn-sm">
                    <i class="fa-solid fa-bowl-food"></i> Beri Pakan (500g)
                </button>
            </div>
        </div>
    </div>

    <!-- ROW 3: TEMPERATURE TREND CHART -->
    <div class="card p-4 sm:p-5">
        <div class="flex items-center justify-between gap-2 mb-3">
            <div class="section-title">
                <i class="fa-solid fa-chart-line"></i>
                <span>Tren Suhu Real-Time</span>
            </div>
            <a href="{{ route('temperature') }}" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700 transition">
                Detail Monitoring <i class="fa-solid fa-arrow-right text-[9px] ml-0.5"></i>
            </a>
        </div>
        <div class="h-52 sm:h-64">
            <canvas id="overviewChart"></canvas>
        </div>
    </div>

    <!-- ROW 4: EMERGENCY STATUS INLINE BANNER -->
    <div class="card p-4 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg {{ $emergencies->whereNull('resolved_at')->count() > 0 ? 'bg-red-50 text-red-500' : 'bg-emerald-50 text-emerald-600' }} flex items-center justify-center text-sm shrink-0">
                <i class="fa-solid {{ $emergencies->whereNull('resolved_at')->count() > 0 ? 'fa-triangle-exclamation' : 'fa-shield-halved' }}"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-700">
                    {{ $emergencies->whereNull('resolved_at')->count() > 0 ? 'Emergency Suhu Aktif' : 'Status Sistem Normal' }}
                </p>
                <p class="text-[11px] text-slate-400">
                    {{ $emergencies->whereNull('resolved_at')->first()?->condition_type ?? 'Semua parameter dalam batas aman.' }}
                </p>
            </div>
        </div>
        <a href="{{ route('emergency') }}" class="btn btn-secondary btn-xs shrink-0">Detail</a>
    </div>

</div>
@endsection

@push('scripts')
<script>
    /* ── Chart Setup ── */
    const ctx = document.getElementById('overviewChart').getContext('2d');
    let grad = ctx.createLinearGradient(0, 0, 0, 300);
    grad.addColorStop(0, 'rgba(5,150,105,0.25)');
    grad.addColorStop(1, 'rgba(5,150,105,0)');

    const overviewChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [@foreach($tempLogs as $t)'{{ $t->recorded_at->format("H:i") }}',@endforeach],
            datasets: [{
                label: 'Suhu (°C)',
                data: [@foreach($tempLogs as $t){{ $t->temperature }},@endforeach],
                borderColor: '#059669',
                backgroundColor: grad,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 } } },
                y: { grid: { color: '#f8fafc', borderDash: [4,4] }, ticks: { color: '#94a3b8', font: { size: 10 } } }
            },
            interaction: { mode: 'index', intersect: false }
        }
    });

    const ovWaterGauge = new Chart(document.getElementById('ovWaterGauge').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Level Air', 'Kosong'],
            datasets: [{
                data: [{{ $latestWater->water_level }}, Math.max(0, 100 - {{ $latestWater->water_level }})],
                backgroundColor: ['#3b82f6', '#f1f5f9'],
                borderWidth: 0,
                circumference: 180,
                rotation: 270,
            }]
        },
        options: { responsive: true, maintainAspectRatio: true, cutout: '75%', plugins: { legend: { display: false }, tooltip: { enabled: false } }, animation: { animateRotate: true, animateScale: false } },
        plugins: [gaugeNeedle]
    });

    /* ── Real-time Polling ── */
    async function fetchRealtimeState() {
        try {
            const data = await (await fetch('/api/state')).json();
            if (data.latest_temp) {
                document.getElementById('ov-temp').innerText = parseFloat(data.latest_temp.temperature).toFixed(1);
                
                const fluid = document.getElementById('ov-thermo-fluid');
                const bulb = document.getElementById('ov-thermo-bulb');
                fluid.style.height = Math.min(Math.max((data.latest_temp.temperature / 50) * 100, 10), 100) + '%';
                fluid.className = 'thermo-fluid ' + (data.latest_temp.status === 'Normal' ? 'bg-gradient-to-t from-emerald-500 to-emerald-400' : 'bg-gradient-to-t from-red-500 to-red-400');
                bulb.className = 'thermo-bulb ' + (data.latest_temp.status === 'Normal' ? 'bg-emerald-500' : 'bg-red-500');

                const b = document.getElementById('ov-temp-badge');
                b.innerText = data.latest_temp.status;
                b.className = 'badge ' + (data.latest_temp.status === 'Normal' ? 'badge-success' : 'badge-danger') + ' text-[9px] px-1.5 py-0.5';
                document.getElementById('ov-lamp').innerText = data.latest_temp.lamp_status;
                document.getElementById('ov-fan').innerText = data.latest_temp.fan_status;
            }
            if (data.latest_water) {
                document.getElementById('ov-water').innerText = parseFloat(data.latest_water.water_level).toFixed(0);
                
                ovWaterGauge.data.datasets[0].data = [data.latest_water.water_level, Math.max(0, 100 - data.latest_water.water_level)];
                ovWaterGauge.data.datasets[0].backgroundColor = [ data.latest_water.water_level > 30 ? '#3b82f6' : '#ef4444', '#f1f5f9' ];
                ovWaterGauge.update();
                
                document.getElementById('ov-pump').innerText = data.latest_water.pump_status;
                document.getElementById('ov-pump-desc').innerText = data.latest_water.state_desc;
            }
            if (data.last_feeding) {
                document.getElementById('ov-last-feed').innerText = data.last_feeding.formatted_time;
                document.getElementById('ov-last-feed-src').innerText = data.last_feeding.source;
            }
            if (data.next_schedule) {
                document.getElementById('ov-next-time').innerText = data.next_schedule.time;
                document.getElementById('ov-next-label').innerText = data.next_schedule.label;
            }
            if (data.active_emergency) {
                pushNotification('🚨 ' + data.active_emergency.condition_type, data.active_emergency.formatted_summary, 'emergency');
            }
        } catch(e) { console.error(e); }
    }
    setInterval(fetchRealtimeState, 4000);

    /* ── Quick Feed ── */
    async function triggerManualFeed() {
        const btn = document.getElementById('btn-quick-feed');
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Proses...';
        try {
            const r = await (await fetch('/api/feed/manual', {
                method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({portion_grams:500})
            })).json();
            if (r.success) { pushNotification('Pakan Manual', 'Porsi 500g telah diberikan.', 'info'); fetchRealtimeState(); }
        } catch(e) { console.error(e); }
        btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-bowl-food"></i> Beri Pakan (500g)';
    }
</script>
@endpush
