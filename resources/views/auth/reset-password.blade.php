@extends('layouts.app')

@section('title', 'Choose new password — '.setting('store_name', 'Lax Jewellers'))

@section('content')
<div class="auth-wrap">
    <div class="auth-card reveal in">
        <div class="eyebrow">Password reset</div>
        <h1 class="display">New password</h1>
        <p class="auth-sub">Choose a new password for your account.</p>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
                @error('email')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password">New password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
                @error('password')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-solid">Reset password</button>
        </form>
    </div>
</div>
@endsection
