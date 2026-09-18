<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Log in') }} | {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center text-gray-900">
    <div class="w-full max-w-sm bg-white rounded-xl border border-gray-200 shadow-sm p-8">
        <h1 class="text-xl font-semibold mb-6">{{ __('Log in') }}</h1>

        <form method="POST" action="{{ route('signer.portal.login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1" for="email">{{ __('Email address') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-lg border-gray-300 border px-3 py-2">
                @error('email')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" for="password">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded-lg border-gray-300 border px-3 py-2">
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="rounded">
                {{ __('Remember me') }}
            </label>

            <button class="w-full bg-gray-900 text-white rounded-lg py-2 font-medium hover:bg-gray-800">
                {{ __('Log in') }}
            </button>
        </form>
    </div>
</body>
</html>
