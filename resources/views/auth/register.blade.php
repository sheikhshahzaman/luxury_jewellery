@extends('layouts.app')

@section('title', 'Create account — '.setting('store_name', 'Lax Jewellers'))

@section('content')
<div class="auth-wrap">
    <div class="auth-card reveal in">
        <div class="eyebrow">Join the circle</div>
        <h1 class="display">Create account</h1>
        <p class="auth-sub">Track orders, save your wishlist and check out faster.</p>

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="field">
                <label for="name">Full name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                @error('email')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="phone">Phone <span style="text-transform:none;letter-spacing:0">(optional)</span></label>
                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                @error('phone')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
                @error('password')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-solid">Create account</button>
        </form>

        <p class="auth-alt">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </div>
</div>
@endsection
