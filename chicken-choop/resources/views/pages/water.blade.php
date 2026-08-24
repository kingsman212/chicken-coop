@extends('layouts.app')

@section('title', 'Air & Pompa — Smart Coop IoT')
@section('page_icon', '💧')
@section('page_title', 'Monitoring Air & Pompa')

@section('content')
<div class="space-y-5 animate-in">

    <!-- TOP STATUS CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        <!-- Water Level Card -->
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="metric-label">Level Air Minum</span>
                <span id="wt-status" class="badge {{ $latestWater->water_level > 30 ? 'badge-success' : 'badge-danger' }}">
                    {{ $latestWater->water_level > 30 ? 'Normal' : 'Rendah' }}
                </span>
            </div>
            <div class="w-full max-w-[200px] mx-auto mt-6 mb-2">
                <canvas id="waterGaugeChart"></canvas>
                <div class="flex flex-col items-center mt-2 pointer-events-none">
                    <div class="flex items-baseline">
                        <span id="wt-val" class="metric-value text-3xl sm:text-4xl">{{ number_format($latestWater->water_level, 0) }}</span>
                        <span class="metric-unit text-lg">%</span>
                    </div>
                    <p class="text-[9px] text-slate-400 mt-0.5">Min Auto-Fill: {{ number_format($settings->water_min ?? 20, 0) }}%</p>
                </div>
            </div>
        </div>

        <!-- Pump Control Card -->
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2.5">
                    <div class="icon-box bg-indigo-50 text-indigo-500">
                        <i class="fa-solid fa-faucet-drip"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-700">Pompa Air</p>
                        <p class="text-[10px] text-slate-400">Override: <span id="wt-pump-ov" class="font-semibold text-slate-600">{{ $settings->pump_manual_override }}</span></p>
                    </div>
                </div>
                <span id="wt-pump-st" class="text-lg font-extrabold {{ $latestWater->pump_status === 'ON' ? 'text-indigo-600' : 'text-slate-300' }}">
                    {{ $latestWater->pump_status }}
                </span>
            </div>
            <p id="wt-desc" class="text-[10px] text-slate-400 mt-1 mb-3">{{ $latestWater->state_desc }}</p>
            <div class="flex gap-2">
                <button onclick="controlActuator('pump','ON')" class="btn btn-secondary btn-xs flex-1">ON</button>
                <button onclick="controlActuator('pump','OFF')" class="btn btn-secondary btn-xs flex-1">OFF</button>
                <button onclick="controlActuator('pump','AUTO')" class="btn btn-accent btn-xs px-3">AUTO</button>
            </div>
        </div>

        <!-- System Info Card -->
        <div class="card p-5 sm:col-span-2 lg:col-span-1">
            <p class="metric-label mb-3">Informasi Otomatisasi</p>
            <div class="space-y-2.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span> Ambang Pengisian Auto
                    </span>
                    <span class="font-bold text-slate-700 font-mono">{{ $settings->water_min ?? 20 }}%</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-500 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Mode Kontrol System
                    </span>
                    <span class="font-bold text-slate-700 font-mono">{{ strtoupper($settings->control_mode) }}</span>
                </div>
            </div>
        </div>

    </div>

    <!-- WATER LEVEL CHART -->
    <div class="card p-4 sm:p-5">
        <div class="section-title mb-4">
            <i class="fa-solid fa-chart-area text-blue-500"></i>
            <span>Grafik Level Air Minum</span>
        </div>
        <div class="h-60 sm:h-72">
            <canvas id="waterChart"></canvas>
        </div>
    </div>

    <!-- LOG TABLE -->
    <div class="card p-4 sm:p-5">
        <div class="section-title mb-3">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Log Histori Pompa & Level Air</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu Record</th>
                        <th>Level Air</th>
                        <th>Status Pompa</th>
                        <th>Keterangan State</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($waterLogs->reverse() as $log)
                    <tr>
                        <td class="font-mono text-slate-400">{{ $log->recorded_at->format('d/m H:i:s') }}</td>
                        <td class="font-bold text-slate-700 font-mono">{{ number_format($log->water_level,0) }}%</td>
                        <td class="font-bold {{ $log->pump_status === 'ON' ? 'text-indigo-600' : 'text-slate-300' }}">{{ $log->pump_status }}</td>
                        <td class="text-slate-500 text-xs truncate max-w-[250px]">{{ $log->state_desc }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    const wc = document.getElementById('waterChart').getContext('2d');
    let wGrad = wc.createLinearGradient(0, 0, 0, 300);
    wGrad.addColorStop(0, 'rgba(37,99,235,0.25)');
    wGrad.addColorStop(1, 'rgba(37,99,235,0)');

    new Chart(wc, {
        type: 'line',
        data: {
            labels: [@foreach($waterLogs as $w)'{{ $w->recorded_at->format("H:i") }}',@endforeach],
            datasets: [{
                label: 'Level Air (%)',
                data: [@foreach($waterLogs as $w){{ $w->water_level }},@endforeach],
                borderColor: '#2563eb',
                backgroundColor: wGrad,
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
            plugins: { legend: { labels: { color: '#64748b', font: { size: 11 }, usePointStyle: true, boxWidth: 8 } } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 } } },
                y: { grid: { color: '#f8fafc', borderDash: [4,4] }, ticks: { color: '#94a3b8', font: { size: 10 } }, min: 0, max: 100 }
            },
            interaction: { mode: 'index', intersect: false }
        }
    });

    const waterGaugeChart = new Chart(document.getElementById('waterGaugeChart').getContext('2d'), {
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
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '80%',
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false }
            },
            animation: { animateRotate: true, animateScale: false }
        },
        plugins: [gaugeNeedle]
    });

    async function fetchRealtimeState() {
        try {
            const d = await (await fetch('/api/state')).json();
            if (d.latest_water) {
                document.getElementById('wt-val').innerText = parseFloat(d.latest_water.water_level).toFixed(0);
                
                waterGaugeChart.data.datasets[0].data = [d.latest_water.water_level, Math.max(0, 100 - d.latest_water.water_level)];
                waterGaugeChart.data.datasets[0].backgroundColor = [
                    d.latest_water.water_level > 30 ? '#3b82f6' : '#ef4444', 
                    '#f1f5f9'
                ];
                waterGaugeChart.update();

                document.getElementById('wt-status').innerText = d.latest_water.water_level > 30 ? 'Normal' : 'Rendah';
                document.getElementById('wt-pump-st').innerText = d.latest_water.pump_status;
                document.getElementById('wt-desc').innerText = d.latest_water.state_desc;
            }
        } catch(e) { console.error(e); }
    }
    setInterval(fetchRealtimeState, 4000);
</script>
@endpush
