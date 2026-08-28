@extends('layouts.app')

@section('title', 'Pengaturan Sistem — Smart Coop IoT')
@section('page_icon', '⚙️')
@section('page_title', 'Pengaturan Ambang Batas & Mode')

@section('content')
<div class="max-w-xl mx-auto space-y-5 animate-in">

    <div class="card p-5 sm:p-6">
        <div class="section-title mb-1">
            <i class="fa-solid fa-sliders text-emerald-600"></i>
            <span>Pengaturan Threshold Sensor & Mode Control</span>
        </div>
        <p class="text-xs text-slate-400 mb-5">Perubahan nilai batas sensor akan disimpan ke database MySQL dan langsung disinkronkan ke topik MQTT <code class="font-mono text-emerald-600 bg-emerald-50 px-1 py-0.5 rounded text-[11px]">chickencoop/config/settings</code>.</p>

        <form id="settings-page-form" onsubmit="savePageSettings(event)" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Batas Minimum Suhu (°C)</label>
                <input type="number" step="0.5" min="10" max="45" id="page-temp-min" value="{{ number_format($settings->temp_min, 1) }}" required class="form-input text-xs font-mono">
                <p class="text-[10px] text-slate-400 mt-1"><i class="fa-solid fa-circle-info text-[9px] mr-0.5"></i> Jika Suhu < Min &rarr; Lampu Pemanas otomatis AKTIF (dalam Mode Auto). Rentang: 10°C – 45°C.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Batas Maksimum Suhu (°C)</label>
                <input type="number" step="0.5" min="15" max="50" id="page-temp-max" value="{{ number_format($settings->temp_max, 1) }}" required class="form-input text-xs font-mono">
                <p class="text-[10px] text-slate-400 mt-1"><i class="fa-solid fa-circle-info text-[9px] mr-0.5"></i> Jika Suhu > Max &rarr; Kipas Pendingin otomatis AKTIF (dalam Mode Auto). Rentang: > Min hingga 50°C.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Batas Minimum Level Air (%)</label>
                <input type="number" step="1" min="5" max="90" id="page-water-min" value="{{ number_format($settings->water_min, 0) }}" required class="form-input text-xs font-mono">
                <p class="text-[10px] text-slate-400 mt-1"><i class="fa-solid fa-circle-info text-[9px] mr-0.5"></i> Jika Level Air < Min &rarr; Pompa Air otomatis AKTIF mengisi wadah (dalam Mode Auto).</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Mode Kontrol Global System</label>
                <select id="page-control-mode" class="form-select text-xs">
                    <option value="auto" {{ $settings->control_mode === 'auto' ? 'selected' : '' }}>Mode Otomatis (Rule-based Actuator System Active)</option>
                    <option value="manual" {{ $settings->control_mode === 'manual' ? 'selected' : '' }}>Mode Manual Override (Pengguna Memegang Kontrol Penuh)</option>
                </select>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                <button type="submit" id="btn-save-settings" class="btn btn-primary btn-sm w-full sm:w-auto">
                    <i class="fa-solid fa-floppy-disk text-[10px]"></i> Simpan & Push ke MQTT
                </button>
            </div>
        </form>
    </div>

    <!-- DATABASE MAINTENANCE & TRUNCATE TABLES CARD -->
    <div class="card p-5 sm:p-6 border-red-100">
        <div class="flex items-center justify-between gap-2 mb-1">
            <div class="section-title text-red-600">
                <i class="fa-solid fa-trash-can text-red-600"></i>
                <span>Manajemen & Pembersihan Data Database MySQL</span>
            </div>
            <button onclick="truncateTable('all')" class="btn btn-danger btn-xs" title="Hapus semua baris riwayat data log">
                <i class="fa-solid fa-dumpster-fire text-[10px]"></i> Kosongkan Semua Log
            </button>
        </div>
        <p class="text-xs text-slate-400 mb-4">Fitur ini digunakan untuk mengosongkan (<span class="font-mono text-red-500 font-semibold">TRUNCATE</span>) data riwayat log pada database MySQL per tabel atau sekaligus.</p>

        <div class="space-y-3">
            <!-- 1. Temperature Logs -->
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-700">1. Log Suhu & Aktuator</span>
                        <code class="text-[10px] text-slate-400 font-mono bg-white px-1.5 py-0.5 rounded border border-slate-200">temperature_logs</code>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Jumlah Data: <span id="count-temp" class="font-mono font-bold text-slate-700">{{ number_format($tableCounts['temp'] ?? 0) }}</span> baris</p>
                </div>
                <button onclick="truncateTable('temp')" class="btn btn-secondary btn-xs text-red-600 hover:bg-red-50 border-red-200">
                    <i class="fa-solid fa-trash text-[9px]"></i> Kosongkan Tabel
                </button>
            </div>

            <!-- 2. Water Pump Logs -->
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-700">2. Log Level Air & Pompa</span>
                        <code class="text-[10px] text-slate-400 font-mono bg-white px-1.5 py-0.5 rounded border border-slate-200">water_pump_logs</code>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Jumlah Data: <span id="count-water" class="font-mono font-bold text-slate-700">{{ number_format($tableCounts['water'] ?? 0) }}</span> baris</p>
                </div>
                <button onclick="truncateTable('water')" class="btn btn-secondary btn-xs text-red-600 hover:bg-red-50 border-red-200">
                    <i class="fa-solid fa-trash text-[9px]"></i> Kosongkan Tabel
                </button>
            </div>

            <!-- 3. Feeding Logs -->
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-700">3. Log Riwayat Pakan</span>
                        <code class="text-[10px] text-slate-400 font-mono bg-white px-1.5 py-0.5 rounded border border-slate-200">feeding_logs</code>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Jumlah Data: <span id="count-feeding" class="font-mono font-bold text-slate-700">{{ number_format($tableCounts['feeding'] ?? 0) }}</span> baris</p>
                </div>
                <button onclick="truncateTable('feeding')" class="btn btn-secondary btn-xs text-red-600 hover:bg-red-50 border-red-200">
                    <i class="fa-solid fa-trash text-[9px]"></i> Kosongkan Tabel
                </button>
            </div>

            <!-- 4. Emergency Logs -->
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-700">4. Log Emergency Suhu</span>
                        <code class="text-[10px] text-slate-400 font-mono bg-white px-1.5 py-0.5 rounded border border-slate-200">temperature_emergencies</code>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">Jumlah Data: <span id="count-emergency" class="font-mono font-bold text-slate-700">{{ number_format($tableCounts['emergency'] ?? 0) }}</span> baris</p>
                </div>
                <button onclick="truncateTable('emergency')" class="btn btn-secondary btn-xs text-red-600 hover:bg-red-50 border-red-200">
                    <i class="fa-solid fa-trash text-[9px]"></i> Kosongkan Tabel
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    async function savePageSettings(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-settings');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-[10px]"></i> Menyimpan...';

        const temp_min = document.getElementById('page-temp-min').value;
        const temp_max = document.getElementById('page-temp-max').value;
        const water_min = document.getElementById('page-water-min').value;
        const control_mode = document.getElementById('page-control-mode').value;

        try {
            const res = await fetch('/api/settings/update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ temp_min, temp_max, water_min, control_mode })
            });
            const result = await res.json();
            if (res.ok && result.success) {
                pushNotification('Pengaturan Disimpan', 'Konfigurasi threshold berhasil disimpan & dikirim via MQTT.', 'info');
                setTimeout(() => location.reload(), 1000);
            } else {
                let errorMsg = result.message || 'Gagal menyimpan pengaturan.';
                if (result.errors) {
                    errorMsg = Object.values(result.errors).flat().join('\n');
                }
                alert('Gagal menyimpan pengaturan:\n' + errorMsg);
            }
        } catch (err) {
            console.error('Save settings error:', err);
            alert('Terjadi kesalahan jaringan saat menyimpan pengaturan.');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk text-[10px]"></i> Simpan & Push ke MQTT';
    }

    async function truncateTable(tableKey) {
        const tableNames = {
            'temp': 'Log Suhu (temperature_logs)',
            'water': 'Log Air & Pompa (water_pump_logs)',
            'feeding': 'Riwayat Pakan (feeding_logs)',
            'emergency': 'Riwayat Emergency (temperature_emergencies)',
            'all': 'SEMUA TABEL LOG DATABASE'
        };

        const targetName = tableNames[tableKey] || tableKey;
        if (!confirm(`⚠️ PERINGATAN:\nApakah Anda yakin ingin mengosongkan data ${targetName}?\n\nSemua data historis pada tabel tersebut akan dihapus permanen.`)) {
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
                if (result.counts) {
                    if (document.getElementById('count-temp')) document.getElementById('count-temp').innerText = result.counts.temp;
                    if (document.getElementById('count-water')) document.getElementById('count-water').innerText = result.counts.water;
                    if (document.getElementById('count-feeding')) document.getElementById('count-feeding').innerText = result.counts.feeding;
                    if (document.getElementById('count-emergency')) document.getElementById('count-emergency').innerText = result.counts.emergency;
                }
            } else {
                alert('Gagal mengosongkan tabel: ' + (result.message || 'Terjadi kesalahan'));
            }
        } catch (err) {
            console.error('Truncate table error:', err);
            alert('Terjadi kesalahan saat memproses truncate database.');
        }
    }
</script>
@endpush
