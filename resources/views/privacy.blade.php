<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy · {{ $business['business_name'] }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4 sm:px-6">
            <img src="{{ asset('images/logo-tile.png') }}" alt="" class="size-10">
            <span class="leading-tight">
                <span class="block font-extrabold text-brand-800">{{ $business['business_name'] }}</span>
                <span class="block text-[11px] font-semibold tracking-[0.14em] text-brand-600 uppercase">Sales Monitoring System</span>
            </span>
        </div>
    </header>

    <main class="mx-auto max-w-3xl animate-content-in px-4 py-8 sm:px-6">
        <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Privacy Policy</h1>
        <p class="mt-1 text-sm text-slate-500">Effective October 16, 2026</p>

        <div class="card mt-6 space-y-6 text-[15px] leading-relaxed [&_h2]:mb-2 [&_h2]:text-base [&_h2]:font-bold [&_h2]:text-slate-900 [&_li]:ml-5 [&_li]:list-disc [&_ul]:space-y-1">
            <section>
                <p>
                    This policy explains how {{ $business['business_name'] }} ("we") handles information in the FRT System mobile app
                    and the owner's web dashboard. The app is a business tool used only by our own staff (the owner and authorized
                    operators) to record rice milling and tahop sales. It is not for the general public and has no ads.
                </p>
            </section>

            <section>
                <h2>Information we collect</h2>
                <ul>
                    <li><strong>Staff accounts:</strong> name, username and password for each operator and the owner. Passwords are stored
                        only as secure hashes. The offline PIN is kept on the phone as a salted hash; the PIN itself is never stored.</li>
                    <li><strong>Customer records:</strong> name, address or barangay, contact number, age and Facebook name, when the
                        customer chooses to give them. Only the name is required.</li>
                    <li><strong>Transactions:</strong> date and time, sacks and kilograms milled, prices, amounts paid and change, tahop,
                        voids (with the reason and who approved them), and the daily cash count.</li>
                    <li><strong>Device information:</strong> a random ID the app creates for each phone and a short device code (e.g. "A"),
                        used to tell phones apart and to number receipts.</li>
                </ul>
                <p class="mt-2">We do <strong>not</strong> collect location, contacts, photos, camera or microphone data, and the app uses no
                    advertising or analytics services.</p>
            </section>

            <section>
                <h2>How we use it</h2>
                <ul>
                    <li>To record sales, print or share receipts, and compute daily and monthly reports.</li>
                    <li>To check the cash at the end of each day and to see who recorded or voided a transaction.</li>
                    <li>To keep customer details so repeat customers can be chosen quickly.</li>
                </ul>
            </section>

            <section>
                <h2>Where it is stored</h2>
                <p>
                    Records are saved first on the phone so the app works without signal, then sent over an encrypted connection (HTTPS)
                    to our server, hosted on DigitalOcean in Singapore. Only the owner and signed-in operators can see the data.
                </p>
            </section>

            <section>
                <h2>Sharing</h2>
                <p>We do not sell, rent or share this information with anyone. It is used only for running our business, unless the law
                    requires us to disclose it.</p>
            </section>

            <section>
                <h2>How long we keep it</h2>
                <p>Sales records are kept for as long as we need them for our business and tax records. Staff accounts can be deactivated
                    by the owner at any time.</p>
            </section>

            <section>
                <h2>Your choices</h2>
                <p>
                    Customers and staff may ask us to see, correct or delete their personal details. Contact us using the details below
                    and we will act on the request within 30 days, keeping only what the law requires us to keep.
                </p>
            </section>

            <section>
                <h2>Children</h2>
                <p>The app is for our adult staff only and is not meant for children.</p>
            </section>

            <section>
                <h2>Contact us</h2>
                <p>
                    {{ $business['business_name'] }}<br>
                    {{ $business['business_address'] }}<br>
                    Contact: {{ $business['business_contact'] }}
                </p>
            </section>

            <section>
                <h2>Changes</h2>
                <p>If we change this policy, we will update this page and the date above.</p>
            </section>
        </div>
    </main>

    <footer class="mx-auto max-w-3xl px-4 pb-8 text-xs text-slate-400 sm:px-6">
        © {{ now()->year }} {{ $business['business_name'] }}
    </footer>
</body>
</html>
