<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen text-gray-900">
    <nav class="bg-gray-900 text-white">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center gap-6">
            <a href="{{ route('signer.portal.dashboard') }}" class="font-semibold">{{ __('Document Sign') }}</a>
            <a href="{{ route('signer.portal.dashboard') }}" class="text-sm text-gray-300 hover:text-white">{{ __('Dashboard') }}</a>
            <a href="{{ route('signer.portal.customers.index') }}" class="text-sm text-gray-300 hover:text-white">{{ __('Customers') }}</a>
            <a href="{{ route('signer.portal.documents.index') }}" class="text-sm text-gray-300 hover:text-white">{{ __('Documents') }}</a>
            <form method="POST" action="{{ route('signer.portal.logout') }}" class="ml-auto">
                @csrf
                <button class="text-sm text-gray-300 hover:text-white">{{ __('Log out') }}</button>
            </form>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg bg-green-100 border border-green-300 text-green-800 px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-red-100 border border-red-300 text-red-800 px-4 py-3">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
