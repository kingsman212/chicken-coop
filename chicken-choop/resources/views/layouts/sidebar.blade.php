<!-- SIDEBAR INNER CONTENT -->
<div class="flex-1 flex flex-col justify-between h-full">
    <div>
        <!-- Brand -->
        <div class="flex items-center gap-3 pb-4 mb-4 border-b border-slate-100">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white text-base shadow-sm">
                <i class="fa-solid fa-feather"></i>
            </div>
            <div>
                <h1 class="text-sm font-bold text-slate-800 tracking-tight">Smart Coop</h1>
                <p class="text-[10px] font-semibold text-emerald-600 uppercase tracking-wider">IoT Controller</p>
            </div>
            <!-- Close btn (mobile only) -->
            <button onclick="toggleSidebar()" class="ml-auto md:hidden w-7 h-7 rounded-lg hover:bg-slate-100 flex items-center justify-center text-slate-400">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <!-- Navigation Links -->
        <nav class="space-y-1">
            <a href="{{ route('overview') }}" class="sidebar-link {{ request()->routeIs('overview') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-pie w-4 text-center text-xs"></i>
                <span>Overview</span>
            </a>
            <a href="{{ route('temperature') }}" class="sidebar-link {{ request()->routeIs('temperature') ? 'active' : '' }}">
                <i class="fa-solid fa-temperature-half w-4 text-center text-xs"></i>
                <span>Monitoring Suhu</span>
            </a>
            <a href="{{ route('water') }}" class="sidebar-link {{ request()->routeIs('water') ? 'active' : '' }}">
                <i class="fa-solid fa-droplet w-4 text-center text-xs"></i>
                <span>Air & Pompa</span>
            </a>
            <a href="{{ route('feeding') }}" class="sidebar-link {{ request()->routeIs('feeding') ? 'active' : '' }}">
                <i class="fa-solid fa-wheat-awn w-4 text-center text-xs"></i>
                <span>Manajemen Pakan</span>
            </a>
            <a href="{{ route('emergency') }}" class="sidebar-link {{ request()->routeIs('emergency') ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation w-4 text-center text-xs"></i>
                <span>Emergency Suhu</span>
            </a>
            <a href="{{ route('logs') }}" class="sidebar-link {{ request()->routeIs('logs') ? 'active' : '' }}">
                <i class="fa-solid fa-database w-4 text-center text-xs"></i>
                <span>Log & Data</span>
            </a>
            <a href="{{ route('settings') }}" class="sidebar-link {{ request()->routeIs('settings') ? 'active' : '' }}">
                <i class="fa-solid fa-sliders w-4 text-center text-xs"></i>
                <span>Pengaturan</span>
            </a>
        </nav>
    </div>

    <!-- Footer: System Status & Control Mode -->
    <div class="pt-3 border-t border-slate-100 space-y-2">
        <div class="flex items-center justify-between px-1">
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Mode Control</span>
            <button onclick="toggleGlobalControlMode()" class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200/60 transition flex items-center gap-1">
                <i class="fa-solid fa-robot text-[9px]"></i>
                <span id="sidebar-control-mode-text">{{ strtoupper($settings->control_mode ?? 'AUTO') }}</span>
            </button>
        </div>
        <div class="flex items-center justify-between px-2.5 py-1.5 rounded-lg bg-slate-50 border border-slate-100 text-[10px] font-medium text-slate-500">
            <span class="flex items-center gap-1.5">
                <span class="pulse-dot bg-emerald-500"></span>
                <span id="mqtt-sidebar-text">Eclipse Mosquitto</span>
            </span>
            <i class="fa-solid fa-wifi text-[9px] text-slate-400"></i>
        </div>

        @auth
        <!-- User Profile Footer -->
        <div class="pt-2 mt-2 border-t border-slate-100 flex items-center justify-between px-1">
            <div class="flex items-center gap-2 overflow-hidden">
                <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs flex-shrink-0">
                    {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                </div>
                <div class="truncate">
                    <p class="text-xs font-semibold text-slate-700 truncate leading-tight">{{ Auth::user()->name }}</p>
                    <p class="text-[10px] text-slate-400 truncate">{{ Auth::user()->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0">
                @csrf
                <button type="submit" class="w-7 h-7 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-600 transition flex items-center justify-center" title="Keluar / Logout">
                    <i class="fa-solid fa-right-from-bracket text-xs"></i>
                </button>
            </form>
        </div>
        @endauth
    </div>
</div>
