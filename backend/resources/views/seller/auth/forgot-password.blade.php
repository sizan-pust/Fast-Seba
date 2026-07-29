@extends('layouts.seller.guest')
@section('title', 'Forgot Password')
@section('content')
<div class="card card-md"><div class="card-body"><h2 class="h2 text-center mb-4">Reset seller password</h2><p class="text-secondary">Enter the seller email address. A secure reset link will be sent when the account is active.</p><form method="POST" action="{{ route('seller.password.email') }}">@csrf<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus></div><button class="btn btn-success w-100">Send reset link</button></form></div></div><div class="text-center text-secondary mt-3"><a href="{{ route('seller.login') }}">Return to login</a></div>
@endsection
