@extends('layouts.app')

@section('title', 'Log & Data Historis — Smart Coop IoT')
@section('page_icon', '📊')
@section('page_title', 'Log & Data Historis MySQL')

@section('content')
<div class="space-y-5 animate-in">

    <!-- TAB SWITCHER HEADER -->
    <div class="card p-3.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div class="section-title">
            <i class="fa-solid fa-database text-teal-600"></i>
            <span>Tabel Storage MySQL</span>
        </div>
        <div class="flex flex-wrap gap-1.5 w-full sm:w-auto">
            <a href="?tab=temp" class="btn btn-xs {{ $tab === 'temp' ? 'btn-primary' : 'btn-secondary' }}">
                <i class="fa-solid fa-temperature-half text-[9px]"></i> Suhu & Actuator ({{ number_format($tableCounts['temp'] ?? 0) }})
            </a>
            <a href="?tab=water" class="btn btn-xs {{ $tab === 'water' ? 'btn-primary' : 'btn-secondary' }}">
                <i class="fa-solid fa-droplet text-[9px]"></i> Level Air & Pompa ({{ number_format($tableCounts['water'] ?? 0) }})
            </a>
            <a href="?tab=feeding" class="btn btn-xs {{ $tab === 'feeding' ? 'btn-primary' : 'btn-secondary' }}">
                <i class="fa-solid fa-wheat-awn text-[9px]"></i> Pemberian Pakan ({{ number_format($tableCounts['feeding'] ?? 0) }})
            </a>
            <a href="?tab=emergency" class="btn btn-xs {{ $tab === 'emergency' ? 'btn-primary' : 'btn-secondary' }}">
                <i class="fa-solid fa-triangle-exclamation text-[9px]"></i> Emergency Suhu ({{ number_format($tableCounts['emergency'] ?? 0) }})
            </a>
        </div>
    </div>

    <!-- TAB TABLES -->
    <div class="card p-4 sm:p-5">

        @if($tab === 'temp')
            <div class="flex items-center justify-between gap-2 mb-3">
                <div class="section-title">
                    <i class="fa-solid fa-table"></i>
                    <span>Tabel <code class="font-mono text-xs bg-slate-100 px-1 py-0.5 rounded">temperature_logs</code></span>
                    <span class="text-[11px] text-slate-400 font-normal">({{ number_format($tableCounts['temp'] ?? 0) }} total data)</span>
                </div>
                <button onclick="truncateLogTable('temp', 'temperature_logs')" class="btn btn-danger btn-xs">
                    <i class="fa-solid fa-trash text-[9px]"></i> Kosongkan Tabel Ini
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu Record</th>
                            <th>Suhu (°C)</th>
                            <th>Status Condition</th>
                            <th>Lampu Pemanas</th>
                            <th>Kipas Pendingin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tempLogs as $log)
                            <tr>
                                <td class="font-mono text-slate-400">{{ $log->recorded_at->format('d-m-Y H:i:s') }}</td>
                                <td class="font-mono font-bold text-slate-800">{{ number_format($log->temperature, 1) }}°C</td>
                                <td>
                                    <span class="badge {{ $log->status === 'Normal' ? 'badge-success' : 'badge-danger' }}">
                                        {{ $log->status }}
                                    </span>
                                </td>
                                <td class="font-bold {{ $log->lamp_status === 'ON' ? 'text-yellow-600' : 'text-slate-300' }}">{{ $log->lamp_status }}</td>
                                <td class="font-bold {{ $log->fan_status === 'ON' ? 'text-cyan-600' : 'text-slate-300' }}">{{ $log->fan_status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-slate-400">Belum ada log suhu.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif($tab === 'water')
            <div class="flex items-center justify-between gap-2 mb-3">
                <div class="section-title">
                    <i class="fa-solid fa-table"></i>
                    <span>Tabel <code class="font-mono text-xs bg-slate-100 px-1 py-0.5 rounded">water_pump_logs</code></span>
                    <span class="text-[11px] text-slate-400 font-normal">({{ number_format($tableCounts['water'] ?? 0) }} total data)</span>
                </div>
                <button onclick="truncateLogTable('water', 'water_pump_logs')" class="btn btn-danger btn-xs">
                    <i class="fa-solid fa-trash text-[9px]"></i> Kosongkan Tabel Ini
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu Record</th>
                            <th>Level Air (%)</th>
                            <th>Status Air</th>
                            <th>Status Pompa</th>
                            <th>Keterangan State</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($waterLogs as $wlog)
                            <tr>
                                <td class="font-mono text-slate-400">{{ $wlog->recorded_at->format('d-m-Y H:i:s') }}</td>
                                <td class="font-mono font-bold text-slate-800">{{ number_format($wlog->water_level, 0) }}%</td>
                                <td>
                                    <span class="badge {{ $wlog->water_status === 'Normal' ? 'badge-info' : 'badge-danger' }}">
                                        {{ $wlog->water_status }}
                                    </span>
                                </td>
                                <td class="font-bold {{ $wlog->pump_status === 'ON' ? 'text-indigo-600' : 'text-slate-300' }}">{{ $wlog->pump_status }}</td>
                                <td class="text-slate-500 text-xs truncate max-w-[250px]">{{ $wlog->state_desc }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-slate-400">Belum ada log pompa air.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif($tab === 'feeding')
            <div class="flex items-center justify-between gap-2 mb-3">
                <div class="section-title">
                    <i class="fa-solid fa-table"></i>
                    <span>Tabel <code class="font-mono text-xs bg-slate-100 px-1 py-0.5 rounded">feeding_logs</code></span>
                    <span class="text-[11px] text-slate-400 font-normal">({{ number_format($tableCounts['feeding'] ?? 0) }} total data)</span>
                </div>
                <button onclick="truncateLogTable('feeding', 'feeding_logs')" class="btn btn-danger btn-xs">
                    <i class="fa-solid fa-trash text-[9px]"></i> Kosongkan Tabel Ini
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu Pemberian</th>
                            <th>Sumber Pemberian</th>
                            <th>Jadwal / Keterangan</th>
                            <th>Porsi (Gram)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($feedingLogs as $flog)
                            <tr>
                                <td class="font-mono text-slate-400">{{ $flog->fed_at->format('d-m-Y H:i:s') }}</td>
                                <td>
                                    <span class="badge {{ $flog->source === 'Manual' ? 'badge-warning' : 'badge-purple' }}">
                                        {{ $flog->source }}
                                    </span>
                                </td>
                                <td class="font-medium text-slate-700">{{ $flog->schedule_label }}</td>
                                <td class="font-mono text-slate-600">{{ $flog->portion_grams }}g</td>
                                <td>
                                    <span class="badge badge-success">{{ $flog->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-slate-400">Belum ada log pakan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif($tab === 'emergency')
            <div class="flex items-center justify-between gap-2 mb-3">
                <div class="section-title">
                    <i class="fa-solid fa-table"></i>
                    <span>Tabel <code class="font-mono text-xs bg-slate-100 px-1 py-0.5 rounded">temperature_emergencies</code></span>
                    <span class="text-[11px] text-slate-400 font-normal">({{ number_format($tableCounts['emergency'] ?? 0) }} total data)</span>
                </div>
                <button onclick="truncateLogTable('emergency', 'temperature_emergencies')" class="btn btn-danger btn-xs">
                    <i class="fa-solid fa-trash text-[9px]"></i> Kosongkan Tabel Ini
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu Kejadian</th>
                            <th>Suhu (°C)</th>
                            <th>Jenis Emergency</th>
                            <th>Ringkasan Summary</th>
                            <th>Status Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($emergencies as $emg)
                            <tr>
                                <td class="font-mono text-slate-400">{{ $emg->started_at ? $emg->started_at->format('d-m-Y H:i') : '-' }}</td>
                                <td class="font-mono font-bold text-slate-800">{{ number_format($emg->temperature, 1) }}°C</td>
                                <td>
                                    <span class="badge badge-danger">{{ $emg->condition_type }}</span>
                                </td>
                                <td class="text-slate-500 text-xs truncate max-w-[250px]">{{ $emg->formatted_summary ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $emg->resolved_at ? 'badge-success' : 'badge-danger' }}">
                                        {{ $emg->resolved_at ? $emg->resolved_at->format('d-m-Y H:i') : 'AKTIF' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-slate-400">Belum ada log emergency.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

    </div>

</div>
@endsection

@push('scripts')
<script>
    async function truncateLogTable(tableKey, tableName) {
        if (!confirm(`⚠️ PERINGATAN:\nApakah Anda yakin ingin mengosongkan semua data pada tabel ${tableName}?\n\nData yang telah dihapus tidak dapat dikembalikan.`)) {
            return;
        }

        try {
            const res = await fetch('/api/database/truncate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ table: tableKey })
            });
            const result = await res.json();
            if (result.success) {
                alert('✓ ' + result.message);
                location.reload();
            } else {
                alert('Gagal mengosongkan tabel: ' + (result.message || 'Terjadi kesalahan'));
            }
        } catch (err) {
            console.error('Truncate error:', err);
            alert('Terjadi kesalahan saat memproses truncate database.');
        }
    }
</script>
@endpush
