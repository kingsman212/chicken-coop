@extends('layouts.app')

@section('title', 'Manajemen Pakan — Smart Coop IoT')
@section('page_icon', '🌾')
@section('page_title', 'Manajemen Pakan Kandang')

@section('content')
<div class="space-y-5 animate-in">

    <!-- TOP ROW CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        <!-- Next Schedule -->
        <div class="card p-5">
            <div class="flex items-center gap-2.5 mb-2">
                <div class="icon-box bg-purple-50 text-purple-600">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <span class="metric-label">Jadwal Berikutnya</span>
            </div>
            <p id="fd-next" class="text-3xl font-black text-slate-800 font-mono my-1">{{ $nextSchedule ? $nextSchedule['time'] : '--:--' }}</p>
            <p id="fd-next-lbl" class="text-[11px] text-slate-400 truncate">{{ $nextSchedule ? $nextSchedule['label'] : 'Belum ada jadwal aktif' }}</p>
        </div>

        <!-- Last Feeding -->
        <div class="card p-5">
            <div class="flex items-center gap-2.5 mb-2">
                <div class="icon-box bg-orange-50 text-orange-500">
                    <i class="fa-solid fa-bowl-rice"></i>
                </div>
                <span class="metric-label">Pemberian Pakan Terakhir</span>
            </div>
            <p class="text-sm font-bold text-slate-700 my-1">{{ $lastFeeding ? $lastFeeding->fed_at->format('d/m/Y H:i:s') : '-' }}</p>
            <p class="text-[11px] text-slate-400 truncate">{{ $lastFeeding ? $lastFeeding->source . ' — ' . $lastFeeding->portion_grams . 'g' : 'Belum ada data' }}</p>
        </div>

        <!-- Manual Feed Form -->
        <div class="card p-5 sm:col-span-2 lg:col-span-1">
            <div class="flex items-center gap-2.5 mb-2">
                <div class="icon-box bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-hand-holding-hand"></i>
                </div>
                <span class="metric-label">Feed Manual Now</span>
            </div>
            <div class="flex gap-2 mt-3">
                <div class="relative flex-1">
                    <input type="number" id="feed-gram" value="500" min="50" max="2000" class="form-input text-xs font-mono pr-7">
                    <span class="absolute right-2.5 top-2 text-[10px] text-slate-400 font-semibold">g</span>
                </div>
                <button onclick="triggerManualFeed()" id="btn-feed" class="btn btn-primary btn-sm px-4">
                    <i class="fa-solid fa-bowl-food"></i> Feed
                </button>
            </div>
        </div>

    </div>

    <!-- SCHEDULE LIST TABLE & ADD FORM -->
    <div class="card p-4 sm:p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
            <div class="section-title">
                <i class="fa-solid fa-calendar-days text-purple-600"></i>
                <span>Daftar Jadwal Pemberian Pakan (Tersinkron ke RTC)</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button onclick="syncRtcTime()" id="btn-sync-rtc" class="btn btn-secondary btn-xs" title="Kirim waktu server/komputer saat ini ke modul RTC DS3231 alat">
                    <i class="fa-solid fa-clock-rotate-left text-[10px]"></i> Sinkronkan Jam RTC
                </button>
                <button onclick="syncAllSchedulesToDevice()" id="btn-sync-sched" class="btn btn-secondary btn-xs" title="Kirim seluruh jadwal aktif ke firmware ESP8266">
                    <i class="fa-solid fa-arrows-rotate text-[10px]"></i> Kirim ke Alat
                </button>
                <button onclick="document.getElementById('add-schedule-form').classList.toggle('hidden')" class="btn btn-accent btn-xs">
                    <i class="fa-solid fa-plus text-[9px]"></i> Tambah Jadwal
                </button>
            </div>
        </div>

        <!-- Add Schedule Inline Form -->
        <div id="add-schedule-form" class="hidden bg-slate-50 rounded-xl p-4 mb-4 border border-slate-200/80">
            <form id="form-add-sched" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <label class="text-[10px] font-semibold text-slate-500 uppercase block mb-1">Jam Pemberian</label>
                    <input type="time" name="time" required class="form-input text-xs font-mono">
                </div>
                <div>
                    <label class="text-[10px] font-semibold text-slate-500 uppercase block mb-1">Label Jadwal</label>
                    <input type="text" name="label" placeholder="Contoh: Makan Pagi" required class="form-input text-xs">
                </div>
                <div>
                    <label class="text-[10px] font-semibold text-slate-500 uppercase block mb-1">Porsi (Gram)</label>
                    <input type="number" name="portion_grams" value="500" min="50" max="5000" class="form-input text-xs font-mono">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-full">
                        <i class="fa-solid fa-check text-[9px]"></i> Simpan Jadwal
                    </button>
                </div>
            </form>
        </div>

        <!-- Schedule Table -->
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Jam</th>
                        <th>Label</th>
                        <th>Porsi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allSchedules as $s)
                    <tr>
                        <td class="font-mono font-bold text-slate-800">{{ \Carbon\Carbon::parse($s->time)->format('H:i') }}</td>
                        <td class="font-medium text-slate-700">{{ $s->label }}</td>
                        <td class="font-mono text-slate-600">{{ $s->portion_grams }}g</td>
                        <td>
                            <span class="badge {{ $s->is_active ? 'badge-success' : 'badge-muted' }}">
                                {{ $s->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="space-x-1">
                            <button onclick="toggleSchedule({{ $s->id }}, {{ $s->is_active ? 'false' : 'true' }}, '{{ $s->label }}', '{{ $s->time }}')" class="btn btn-xs {{ $s->is_active ? 'btn-secondary' : 'btn-accent' }}">
                                {{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                            <button onclick="deleteSchedule({{ $s->id }})" class="btn btn-danger btn-xs">
                                <i class="fa-solid fa-trash text-[9px]"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-slate-400">Belum ada jadwal pakan. Klik "+ Tambah Jadwal" di atas.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- FEEDING LOG HISTORY -->
    <div class="card p-4 sm:p-5">
        <div class="section-title mb-3">
            <i class="fa-solid fa-clock-rotate-left text-orange-500"></i>
            <span>Riwayat Pemberian Pakan</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu Pemberian</th>
                        <th>Porsi</th>
                        <th>Sumber</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($feedingLogs as $fl)
                    <tr>
                        <td class="font-mono text-slate-400">{{ $fl->fed_at->format('d/m/Y H:i:s') }}</td>
                        <td class="font-bold text-slate-700 font-mono">{{ $fl->portion_grams }}g</td>
                        <td>
                            <span class="badge {{ $fl->source === 'Manual' ? 'badge-warning' : 'badge-purple' }}">
                                {{ $fl->source }}
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-success">{{ $fl->status }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-slate-400">Belum ada riwayat pakan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    document.getElementById('form-add-sched').addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            const r = await (await fetch('/api/schedules', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(Object.fromEntries(fd))
            })).json();
            if (r.success) location.reload();
        } catch(e) { console.error(e); }
    });

    async function toggleSchedule(id, nextActive, label, time) {
        try {
            const r = await (await fetch('/api/schedules/' + id, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ label, time, is_active: nextActive })
            })).json();
            if (r.success) location.reload();
        } catch(e) { console.error(e); }
    }

    async function deleteSchedule(id) {
        if (!confirm('Apakah Anda yakin ingin menghapus jadwal pakan ini?')) return;
        try {
            const r = await (await fetch('/api/schedules/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })).json();
            if (r.success) location.reload();
        } catch(e) { console.error(e); }
    }

    async function triggerManualFeed() {
        const btn = document.getElementById('btn-feed');
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        const grams = document.getElementById('feed-gram').value;
        try {
            await (await fetch('/api/feed/manual', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ portion_grams: grams })
            })).json();
            location.reload();
        } catch(e) { console.error(e); }
        btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-bowl-food"></i> Feed';
    }

    async function syncRtcTime() {
        const btn = document.getElementById('btn-sync-rtc');
        const origText = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sinkronisasi...';
        try {
            const res = await (await fetch('/api/rtc/sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            })).json();
            if (res.success) {
                alert('✓ ' + res.message + '\nWaktu Server: ' + res.server_time);
            } else {
                alert('Gagal menyinkronkan waktu RTC: ' + res.message);
            }
        } catch(e) {
            console.error(e);
            alert('Terjadi kesalahan saat sinkronisasi RTC');
        }
        btn.disabled = false; btn.innerHTML = origText;
    }

    async function syncAllSchedulesToDevice() {
        const btn = document.getElementById('btn-sync-sched');
        const origText = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...';
        try {
            const res = await (await fetch('/api/schedules/sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            })).json();
            if (res.success) {
                alert('✓ ' + res.message);
            } else {
                alert('Gagal mengirim jadwal: ' + res.message);
            }
        } catch(e) {
            console.error(e);
            alert('Terjadi kesalahan saat mengirim jadwal');
        }
        btn.disabled = false; btn.innerHTML = origText;
    }
</script>
@endpush
