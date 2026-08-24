@extends('layouts.app')

@section('title', 'Emergency Suhu — Smart Coop IoT')
@section('page_icon', '🚨')
@section('page_title', 'Emergency & Alert Suhu')

@section('content')
<div class="space-y-5 animate-in">

    <!-- ACTIVE EMERGENCY BANNER -->
    @php $activeEmergencies = $emergencies->whereNull('resolved_at'); @endphp
    @if($activeEmergencies->count() > 0)
    <div class="card p-4 border-l-4 border-l-red-500 bg-red-50/40">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg bg-red-100 text-red-600 flex items-center justify-center text-base shrink-0">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-red-700 uppercase tracking-wider">{{ $activeEmergencies->count() }} Peringatan Emergency Aktif</h3>
                </div>
                <div class="space-y-2 mt-2">
                    @foreach($activeEmergencies as $em)
                    <div class="p-3 bg-white rounded-lg border border-red-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 shadow-sm">
                        <div>
                            <p class="text-xs font-bold text-slate-800">{{ $em->condition_type }}</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Triggered: <span class="font-mono font-semibold">{{ $em->started_at ? $em->started_at->format('d/m/Y H:i:s') : '-' }}</span> |
                                Suhu: <span class="font-mono font-bold text-red-600">{{ number_format($em->temperature, 1) }}°C</span>
                            </p>
                        </div>
                        <button onclick="resolveEmergency({{ $em->id }})" class="btn btn-accent btn-xs shrink-0 self-start sm:self-auto">
                            <i class="fa-solid fa-check text-[9px]"></i> Resolve Alert
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="card p-4 flex items-center gap-3 border-l-4 border-l-emerald-500 bg-emerald-50/20">
        <div class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-base shrink-0">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div>
            <p class="text-xs font-bold text-emerald-800">Status Sistem Normal</p>
            <p class="text-[11px] text-slate-500">Tidak ada kondisi darurat suhu yang terdeteksi saat ini.</p>
        </div>
    </div>
    @endif

    <!-- EMERGENCY HISTORY TABLE -->
    <div class="card p-4 sm:p-5">
        <div class="section-title mb-3">
            <i class="fa-solid fa-clock-rotate-left text-rose-500"></i>
            <span>Riwayat Kejadian Emergency Suhu</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu Kejadian</th>
                        <th>Kondisi</th>
                        <th>Suhu Recorded</th>
                        <th>Aksi Otomatis</th>
                        <th>Waktu Selesai</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($emergencies as $em)
                    <tr>
                        <td class="font-mono text-slate-400">{{ $em->started_at ? $em->started_at->format('d/m H:i:s') : '-' }}</td>
                        <td class="font-bold text-slate-700">{{ $em->condition_type }}</td>
                        <td class="font-bold font-mono text-slate-800">{{ number_format($em->temperature, 1) }}°C</td>
                        <td class="text-slate-500 text-xs">{{ $em->active_actuators ?? '-' }}</td>
                        <td class="font-mono text-slate-400">{{ $em->resolved_at ? $em->resolved_at->format('d/m H:i:s') : '-' }}</td>
                        <td>
                            <span class="badge {{ $em->resolved_at ? 'badge-success' : 'badge-danger' }}">
                                {{ $em->resolved_at ? 'Resolved' : 'Active' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-slate-400">Belum ada riwayat emergency.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($emergencies->hasPages())
        <div class="mt-4">
            {{ $emergencies->links() }}
        </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
    async function resolveEmergency(id) {
        try {
            const r = await (await fetch('/api/emergency/' + id + '/resolve', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })).json();
            if (r.success) location.reload();
        } catch(e) { console.error(e); }
    }
</script>
@endpush
