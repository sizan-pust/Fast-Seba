@extends('layouts.seller.guest')
@section('title', 'Seller Login')
@section('content')
<div class="card card-md"><div class="card-body">
    <h2 class="h2 text-center mb-4">Sign in to seller panel</h2>
    <form method="POST" action="{{ route('seller.login.attempt') }}" autocomplete="off">@csrf
        <div class="mb-3"><label class="form-label">Email address</label><input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus></div>
        <div class="mb-2"><label class="form-label">Password <span class="form-label-description"><a href="{{ route('seller.password.request') }}">Forgot password?</a></span></label><input type="password" name="password" class="form-control" required></div>
        <label class="form-check mb-3"><input type="checkbox" name="remember" value="1" class="form-check-input"><span class="form-check-label">Remember me</span></label>
        <div class="form-footer"><button type="submit" class="btn btn-success w-100">Sign in</button></div>
    </form>
</div></div>
<div class="text-center text-secondary mt-3">New seller? <a href="{{ route('seller.register') }}">Create seller account</a></div>
@endsection
