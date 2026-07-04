@if (session('success') || session('error') || session('info'))
    <div class="flash-stack" id="flashStack">
        @if (session('success'))
            <div class="flash flash-success"><span class="gem">✦</span> {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="flash flash-error">{{ session('error') }}</div>
        @endif
        @if (session('info'))
            <div class="flash flash-info">{{ session('info') }}</div>
        @endif
    </div>
@endif
