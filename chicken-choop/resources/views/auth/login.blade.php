<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Smart Coop IoT</title>
    <meta name="description" content="Halaman login sistem monitoring dan kontrol kandang ayam Smart Coop IoT.">

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

    <style>
        :root {
            --bg-body: #eef2f7;
            --bg-card: #ffffff;
            --border: #e2e7ed;
            --accent: #059669;
            --accent-hover: #047857;
            --accent-bg: #ecfdf5;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --radius: .875rem;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }

        /* Subtle animated background shapes */
        .bg-glow-1 {
            position: absolute;
            top: -10%;
            left: 20%;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.12) 0%, rgba(238, 242, 247, 0) 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }

        .bg-glow-2 {
            position: absolute;
            bottom: -10%;
            right: 15%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(20, 184, 166, 0.1) 0%, rgba(238, 242, 247, 0) 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 231, 237, 0.9);
            border-radius: 1.25rem;
            box-shadow: 0 10px 40px -10px rgba(15, 23, 42, 0.08), 0 4px 12px -2px rgba(15, 23, 42, 0.04);
        }

        .form-input-group {
            position: relative;
        }

        .form-input-group i.input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.875rem;
            transition: color 0.2s ease;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.6rem;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-primary);
            background: #ffffff;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1);
        }

        .form-input:focus + i.input-icon,
        .form-input-group:focus-within i.input-icon {
            color: var(--accent);
        }

        .btn-submit {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #ffffff;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.75rem 1.5rem;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.25);
        }

        .btn-submit:hover {
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
            transform: translateY(-1px);
        }

        .btn-submit:active {
            transform: translateY(0);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <!-- Ambient glow shapes -->
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <div class="w-full max-w-md z-10">

        <!-- Logo & Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white text-2xl shadow-lg shadow-emerald-500/20 mb-3">
                <i class="fa-solid fa-feather"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">Smart Coop</h1>
            <p class="text-xs font-semibold text-emerald-600 uppercase tracking-widest mt-0.5">IoT Controller System</p>
        </div>

        <!-- Login Card -->
        <div class="login-card p-6 sm:p-8">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-slate-800">Selamat Datang</h2>
                <p class="text-xs text-slate-500 mt-1">Silakan masuk ke akun Anda untuk mengelola sistem kandang.</p>
            </div>

            <!-- Flash Alert Success / Info -->
            @if(session('info'))
                <div class="mb-5 p-3.5 rounded-xl bg-blue-50 border border-blue-200/80 text-blue-700 text-xs flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-info text-sm flex-shrink-0"></i>
                    <span>{{ session('info') }}</span>
                </div>
            @endif

            @if(session('success'))
                <div class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-700 text-xs flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-check text-sm flex-shrink-0"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <!-- Error Alerts -->
            @if($errors->any())
                <div class="mb-5 p-3.5 rounded-xl bg-red-50 border border-red-200/80 text-red-600 text-xs flex items-start gap-2.5">
                    <i class="fa-solid fa-triangle-exclamation text-sm mt-0.5 flex-shrink-0"></i>
                    <div>
                        <span class="font-semibold">Login Gagal:</span>
                        <ul class="list-disc list-inside mt-0.5 space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4" id="login-form">
                @csrf

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">Alamat Email</label>
                    <div class="form-input-group">
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                            placeholder="admin@smartcoop.com"
                            class="form-input @error('email') border-red-400 @enderror">
                        <i class="fa-solid fa-envelope input-icon"></i>
                    </div>
                </div>

                <!-- Password Input -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-semibold text-slate-700">Kata Sandi</label>
                    </div>
                    <div class="form-input-group relative">
                        <input type="password" name="password" id="password" required
                            placeholder="••••••••"
                            class="form-input pr-10 @error('password') border-red-400 @enderror">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs focus:outline-none px-1">
                            <i class="fa-solid fa-eye" id="toggle-icon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="remember" id="remember" class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/20">
                        <span class="text-xs font-medium text-slate-600">Ingat saya di perangkat ini</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full btn-submit flex items-center justify-center gap-2 mt-2" id="btn-submit">
                    <i class="fa-solid fa-right-to-bracket text-sm"></i>
                    <span>Masuk ke Dashboard</span>
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-xs text-slate-400 font-medium">
            <p>Smart Coop IoT Controller &copy; {{ date('Y') }}</p>
            <p class="text-[11px] text-slate-400/80 mt-1">Sistem Otomasi & Monitoring Kandang Ayam</p>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggle-icon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        document.getElementById('login-form').addEventListener('submit', function() {
            const btn = document.getElementById('btn-submit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-sm"></i><span>Memproses...</span>';
        });
    </script>
</body>
</html>
