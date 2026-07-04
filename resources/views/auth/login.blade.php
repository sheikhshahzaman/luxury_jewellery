@extends('layouts.app')

@section('title', 'Sign in — '.setting('store_name', 'Eluxee'))

@section('content')
<div class="auth-wrap">
    <div class="auth-card reveal in">
        <div class="eyebrow">Welcome back</div>
        <h1 class="display">Sign in</h1>
        <p class="auth-sub">Access your orders, wishlist and account details.</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                @error('email')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
                @error('password')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field checkbox-row" style="flex-direction:row">
                <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember" style="letter-spacing:.05em;text-transform:none;font-size:14px">Keep me signed in</label>
            </div>
            <button type="submit" class="btn btn-solid">Sign in</button>
        </form>

        <p class="auth-alt"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
        <p class="auth-alt">New here? <a href="{{ route('register') }}">Create an account</a></p>
    </div>
</div>
@endsection
