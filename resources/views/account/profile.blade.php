@extends('layouts.app')

@section('title', 'Profile — '.setting('store_name', 'Lax Jewellers'))

@section('content')
<div class="container account-wrap">
    <div class="account-layout">
        @include('account.partials.side-nav')

        <div>
            <div class="account-head">
                <h1 class="display">Profile</h1>
                <p>Your personal details and password.</p>
            </div>

            <div class="panel">
                <h3>Personal details</h3>
                <form method="POST" action="{{ route('account.profile.update') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field">
                            <label for="name">Full name</label>
                            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                            @error('name')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                            @error('email')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="phone">Phone</label>
                            <input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}">
                            @error('phone')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <button type="submit" class="btn btn-solid">Save changes</button>
                </form>
            </div>

            <div class="panel">
                <h3>Change password</h3>
                <form method="POST" action="{{ route('account.password.update') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="field full">
                            <label for="current_password">Current password</label>
                            <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
                            @error('current_password')<span class="field-error">{{ $message }}</span>@enderror
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
                    </div>
                    <button type="submit" class="btn btn-solid">Change password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
