<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Document signed') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f3f4f6; color: #111827;
               display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border: 1px solid #d1d5db; border-radius: 8px;
                padding: 40px; text-align: center; max-width: 440px; }
        .check { font-size: 40px; margin-bottom: 12px; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        p { color: #6b7280; }
    </style>
</head>
<body>
<div class="card">
    <div class="check">&#10003;</div>
    <h1>{{ __('Thank you, :name!', ['name' => $signer->name]) }}</h1>
    <p>{{ __('You signed ":title" on :date.', [
        'title' => $signer->document->title,
        'date' => $signer->signed_at?->translatedFormat('j F Y H:i'),
    ]) }}</p>
</div>
</body>
</html>
