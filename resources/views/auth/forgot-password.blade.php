@extends('layouts.app')

@section('title', 'Reset password — '.setting('store_name', 'Lax Jewellers'))

@section('content')
<div class="auth-wrap">
    <div class="auth-card reveal in">
        <div class="eyebrow">Password reset</div>
        <h1 class="display">Forgot password</h1>
        <p class="auth-sub">Enter your email and we will send you a link to set a new password.</p>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                @error('email')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <button type="submit" class="btn btn-solid">Send reset link</button>
        </form>

        <p class="auth-alt">Remembered it? <a href="{{ route('login') }}">Back to sign in</a></p>
    </div>
</div>
@endsection
