@extends('layouts.app')

@section('title', 'Monitoring Suhu — Smart Coop IoT')
@section('page_icon', '🌡️')
@section('page_title', 'Monitoring Suhu & Actuator')

@section('content')
<div class="space-y-5 animate-in">

    <!-- TOP ROW: TEMP GAUGE + LAMP + FAN CONTROL -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <!-- Temperature Gauge Card -->
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="metric-label">Suhu Kandang Real-Time</span>
                <span id="tp-badge" class="badge {{ $latestTemp->status === 'Normal' ? 'badge-success' : 'badge-danger' }}">
                    {{ $latestTemp->status }}
                </span>
            </div>
            <div class="flex items-center gap-8 justify-center mt-6 mb-4">
                <div class="thermo-wrap">
                    <div id="tp-thermo-fluid" class="thermo-fluid {{ $latestTemp->status === 'Normal' ? 'bg-gradient-to-t from-emerald-500 to-emerald-400' : 'bg-gradient-to-t from-red-500 to-red-400' }}" style="height: {{ min(max((($latestTemp->temperature)/50)*100,10),100) }}%;"></div>
                    <div id="tp-thermo-bulb" class="thermo-bulb {{ $latestTemp->status === 'Normal' ? 'bg-emerald-500' : 'bg-red-500' }}"></div>
                </div>
                <div>
                    <div class="flex items-baseline">
                        <span id="tp-val" class="metric-value text-4xl sm:text-5xl">{{ number_format($latestTemp->temperature, 1) }}</span>
                        <span class="metric-unit text-xl">°C</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">Target Range: {{ number_format($settings->temp_min,1) }}°C – {{ number_format($settings->temp_max,1) }}°C</p>
                </div>
            </div>
        </div>

        <!-- Lamp Control Card -->
        <div class="card p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2.5">
                    <div class="icon-box bg-yellow-50 text-yellow-600">
                        <i class="fa-solid fa-lightbulb"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-700">Lampu Pemanas</p>
                        <p class="text-[10px] text-slate-400">Override: <span id="tp-lamp-ov" class="font-semibold text-slate-600">{{ $settings->lamp_manual_override }}</span></p>
                    </div>
                </div>
                <span id="tp-lamp-badge" class="text-lg font-extrabold {{ $latestTemp->lamp_status === 'ON' ? 'text-yellow-600' : 'text-slate-300' }}">
                    {{ $latestTemp->lamp_status }}
                </span>
            </div>
            <div class="flex gap-2 mt-4">
                <button onclick="controlActuator('lamp','ON')" class="btn btn-secondary btn-xs flex-1">ON</button>
                <button onclick="controlActuator('lamp','OFF')" class="btn btn-secondary btn-xs flex-1">OFF</button>
                <button onclick="controlActuator('lamp','AUTO')" class="btn btn-accent btn-xs px-3">AUTO</button>
            </div>
        </div>

        <!-- Fan Control Card -->
        <div class="card p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2.5">
                    <div class="icon-box bg-cyan-50 text-cyan-600">
                        <i class="fa-solid fa-fan"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-700">Kipas Pendingin</p>
                        <p class="text-[10px] text-slate-400">Override: <span id="tp-fan-ov" class="font-semibold text-slate-600">{{ $settings->fan_manual_override }}</span></p>
                    </div>
                </div>
                <span id="tp-fan-badge" class="text-lg font-extrabold {{ $latestTemp->fan_status === 'ON' ? 'text-cyan-600' : 'text-slate-300' }}">
                    {{ $latestTemp->fan_status }}
                </span>
            </div>
            <div class="flex gap-2 mt-4">
                <button onclick="controlActuator('fan','ON')" class="btn btn-secondary btn-xs flex-1">ON</button>
                <button onclick="controlActuator('fan','OFF')" class="btn btn-secondary btn-xs flex-1">OFF</button>
                <button onclick="controlActuator('fan','AUTO')" class="btn btn-accent btn-xs px-3">AUTO</button>
            </div>
        </div>

    </div>

    <!-- FULL CHART -->
    <div class="card p-4 sm:p-5">
        <div class="section-title mb-4">
            <i class="fa-solid fa-chart-line"></i>
            <span>Grafik Fluktuasi Suhu Kandang</span>
        </div>
        <div class="h-60 sm:h-72">
            <canvas id="tempFullChart"></canvas>
        </div>
    </div>

    <!-- LOG TABLE -->
    <div class="card p-4 sm:p-5">
        <div class="section-title mb-3">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Log Histori Suhu Terbaru</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu Record</th>
                        <th>Suhu</th>
                        <th>Status</th>
                        <th>Lampu</th>
                        <th>Kipas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tempLogs->reverse() as $log)
                    <tr>
                        <td class="font-mono text-slate-400">{{ $log->recorded_at->format('d/m H:i:s') }}</td>
                        <td class="font-bold text-slate-700">{{ number_format($log->temperature,1) }}°C</td>
                        <td>
                            <span class="badge {{ $log->status === 'Normal' ? 'badge-success' : 'badge-danger' }}">
                                {{ $log->status }}
                            </span>
                        </td>
                        <td class="font-bold {{ $log->lamp_status === 'ON' ? 'text-yellow-600' : 'text-slate-300' }}">{{ $log->lamp_status }}</td>
                        <td class="font-bold {{ $log->fan_status === 'ON' ? 'text-cyan-600' : 'text-slate-300' }}">{{ $log->fan_status }}</td>
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
    const tfc = document.getElementById('tempFullChart').getContext('2d');
    let tGrad = tfc.createLinearGradient(0, 0, 0, 300);
    tGrad.addColorStop(0, 'rgba(5,150,105,0.25)');
    tGrad.addColorStop(1, 'rgba(5,150,105,0)');

    const tempFullChart = new Chart(tfc, {
        type: 'line',
        data: {
            labels: [@foreach($tempLogs as $t)'{{ $t->recorded_at->format("H:i") }}',@endforeach],
            datasets: [
                {
                    label: 'Suhu (°C)',
                    data: [@foreach($tempLogs as $t){{ $t->temperature }},@endforeach],
                    borderColor: '#059669',
                    backgroundColor: tGrad,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 5
                },
                {
                    label: 'Max Limit',
                    data: Array({{ $tempLogs->count() }}).fill({{ $settings->temp_max }}),
                    borderColor: '#ef4444',
                    borderWidth: 1,
                    borderDash: [4, 4],
                    pointRadius: 0,
                    fill: false
                },
                {
                    label: 'Min Limit',
                    data: Array({{ $tempLogs->count() }}).fill({{ $settings->temp_min }}),
                    borderColor: '#3b82f6',
                    borderWidth: 1,
                    borderDash: [4, 4],
                    pointRadius: 0,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#64748b', font: { size: 11 }, usePointStyle: true, boxWidth: 8 } }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 } } },
                y: { grid: { color: '#f8fafc', borderDash: [4,4] }, ticks: { color: '#94a3b8', font: { size: 10 } } }
            },
            interaction: { mode: 'index', intersect: false }
        }
    });



    async function fetchRealtimeState() {
        try {
            const d = await (await fetch('/api/state')).json();
            if (d.latest_temp) {
                document.getElementById('tp-val').innerText = parseFloat(d.latest_temp.temperature).toFixed(1);
                
                const fluid = document.getElementById('tp-thermo-fluid');
                const bulb = document.getElementById('tp-thermo-bulb');
                fluid.style.height = Math.min(Math.max((d.latest_temp.temperature / 50) * 100, 10), 100) + '%';
                fluid.className = 'thermo-fluid ' + (d.latest_temp.status === 'Normal' ? 'bg-gradient-to-t from-emerald-500 to-emerald-400' : 'bg-gradient-to-t from-red-500 to-red-400');
                bulb.className = 'thermo-bulb ' + (d.latest_temp.status === 'Normal' ? 'bg-emerald-500' : 'bg-red-500');

                document.getElementById('tp-badge').innerText = d.latest_temp.status;
                document.getElementById('tp-badge').className = 'badge ' + (d.latest_temp.status === 'Normal' ? 'badge-success' : 'badge-danger');
                document.getElementById('tp-lamp-badge').innerText = d.latest_temp.lamp_status;
                document.getElementById('tp-fan-badge').innerText = d.latest_temp.fan_status;

                const t = new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
                if (tempFullChart.data.labels.length > 30) {
                    tempFullChart.data.labels.shift();
                    tempFullChart.data.datasets.forEach(ds => ds.data.shift());
                }
                tempFullChart.data.labels.push(t);
                tempFullChart.data.datasets[0].data.push(d.latest_temp.temperature);
                tempFullChart.data.datasets[1].data.push(d.settings.temp_max);
                tempFullChart.data.datasets[2].data.push(d.settings.temp_min);
                tempFullChart.update('none');
            }
        } catch(e) { console.error(e); }
    }
    setInterval(fetchRealtimeState, 3000);
</script>
@endpush
