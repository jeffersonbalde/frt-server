<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · Fabriga Rice Trading</title>
    <meta name="turbo-cache-control" content="no-preview">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-slate-900 antialiased">
    <x-flash />
    <div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
        {{-- Brand panel --}}
        <section class="relative hidden overflow-hidden bg-brand-700 text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
            {{-- Soft rings drawn behind the content, flat colors only. --}}
            <div class="pointer-events-none absolute -top-40 -right-40 size-[28rem] rounded-full border-[60px] border-white/5"></div>
            <div class="pointer-events-none absolute -bottom-48 -left-32 size-[34rem] rounded-full border-[70px] border-white/5"></div>

            <div></div>

            <div class="relative max-w-md">
                <img src="{{ asset('images/logo-mark.png') }}" alt="" class="mb-8 size-24">
                <h1 class="text-4xl leading-tight font-extrabold">Fabriga Rice Trading</h1>
                <p class="mt-2 text-sm font-semibold tracking-[0.2em] text-gold uppercase">Sales Monitoring System</p>
            </div>

            <p class="relative text-xs text-brand-100/80">Mobile rice milling · Mahayag, Zamboanga del Sur</p>
        </section>

        {{-- Sign-in form --}}
        <main class="flex flex-col justify-center px-6 py-10 sm:px-12">
            <div class="mx-auto w-full max-w-sm animate-content-in">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <img src="{{ asset('images/logo-tile.png') }}" alt="" class="size-12">
                    <span class="leading-tight">
                        <span class="block text-lg font-extrabold text-brand-800">Fabriga Rice Trading</span>
                        <span class="block text-[11px] font-semibold tracking-[0.14em] text-brand-600 uppercase">Sales Monitoring System</span>
                    </span>
                </div>

                <h2 class="text-2xl font-bold text-slate-900">Sign in</h2>

                <form method="POST" action="{{ url('/login') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="username" class="label">Username</label>
                        <div class="relative">
                            <x-icon name="user" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-slate-400" />
                            <input id="username" name="username" type="text" value="{{ old('username') }}" placeholder="Enter your username"
                                   class="input pl-10"
                                   required autofocus autocomplete="username">
                        </div>
                        <x-field-error name="username" />
                    </div>

                    <div>
                        <label for="password" class="label">Password</label>
                        <div class="relative">
                            <x-icon name="lock" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-slate-400" />
                            <input id="password" name="password" type="password" placeholder="Enter your password"
                                   class="input pr-11 pl-10" required autocomplete="current-password">
                            <button type="button" data-toggle-password="password"
                                    class="absolute top-1/2 right-2 -translate-y-1/2 rounded p-1.5 text-slate-400 transition-colors hover:text-brand-600"
                                    aria-label="Show password">
                                <x-icon name="eye" class="size-5" data-icon-show />
                                <x-icon name="eye-off" class="hidden size-5" data-icon-hide />
                            </button>
                        </div>
                        <x-field-error name="password" />
                    </div>

                    <button type="submit" class="btn btn-primary mt-2 w-full py-3 tracking-wide uppercase">Log in</button>
                </form>

                <p class="mt-10 text-center text-xs text-slate-400">© {{ now()->year }} Fabriga Rice Trading</p>
            </div>
        </main>
    </div>
</body>
</html>
