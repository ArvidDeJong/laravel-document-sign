<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign document') }}: {{ $signer->document->title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f3f4f6; color: #111827; }
        .container { max-width: 960px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .subtitle { color: #6b7280; margin-bottom: 20px; }
        .pdf-frame { width: 100%; height: 60vh; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; }
        .card { background: #fff; border: 1px solid #d1d5db; border-radius: 8px; padding: 20px; margin-top: 20px; }
        .card h2 { font-size: 16px; margin-bottom: 12px; }
        canvas { width: 100%; height: 180px; border: 1px dashed #9ca3af; border-radius: 6px; background: #fff; touch-action: none; cursor: crosshair; }
        .actions { display: flex; gap: 12px; margin-top: 16px; }
        button { font-size: 15px; padding: 10px 20px; border-radius: 6px; border: 1px solid transparent; cursor: pointer; }
        .btn-primary { background: #111827; color: #fff; }
        .btn-primary:disabled { background: #9ca3af; cursor: not-allowed; }
        .btn-secondary { background: #fff; color: #111827; border-color: #d1d5db; }
        .error { color: #b91c1c; margin-top: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h1>{{ $signer->document->title }}</h1>
    <p class="subtitle">{{ __('Review the document below and place your signature.') }}</p>

    <embed class="pdf-frame" src="{{ route('signer.pdf', $signer) }}" type="application/pdf">

    <div class="card">
        <h2>{{ __('Your signature') }}</h2>
        <canvas id="signature-pad"></canvas>

        <form method="POST" action="{{ route('signer.store', $signer) }}" id="sign-form">
            @csrf
            <input type="hidden" name="signature" id="signature-input">

            <div class="actions">
                <button type="button" class="btn-secondary" id="clear-button">{{ __('Clear') }}</button>
                <button type="submit" class="btn-primary" id="submit-button" disabled>
                    {{ __('Sign document') }}
                </button>
            </div>

            @error('signature')
                <p class="error">{{ $message }}</p>
            @enderror
        </form>
    </div>
</div>

<script>
    (function () {
        const canvas = document.getElementById('signature-pad');
        const input = document.getElementById('signature-input');
        const submitButton = document.getElementById('submit-button');
        const context = canvas.getContext('2d');
        let drawing = false;
        let isEmpty = true;

        function resize() {
            const ratio = window.devicePixelRatio || 1;
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            context.scale(ratio, ratio);
            context.lineWidth = 2;
            context.lineCap = 'round';
            context.strokeStyle = '#111827';
        }

        function position(event) {
            const rect = canvas.getBoundingClientRect();
            const point = event.touches ? event.touches[0] : event;
            return { x: point.clientX - rect.left, y: point.clientY - rect.top };
        }

        function start(event) {
            event.preventDefault();
            drawing = true;
            const { x, y } = position(event);
            context.beginPath();
            context.moveTo(x, y);
        }

        function move(event) {
            if (!drawing) return;
            event.preventDefault();
            const { x, y } = position(event);
            context.lineTo(x, y);
            context.stroke();
            isEmpty = false;
            submitButton.disabled = false;
        }

        function stop() {
            drawing = false;
        }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        window.addEventListener('mouseup', stop);
        window.addEventListener('touchend', stop);

        document.getElementById('clear-button').addEventListener('click', function () {
            context.clearRect(0, 0, canvas.width, canvas.height);
            isEmpty = true;
            submitButton.disabled = true;
        });

        document.getElementById('sign-form').addEventListener('submit', function (event) {
            if (isEmpty) {
                event.preventDefault();
                return;
            }
            input.value = canvas.toDataURL('image/png');
        });

        resize();
    })();
</script>
</body>
</html>
