@extends('layouts.seller.guest')
@section('title', 'Reset Password')
@section('content')
<div class="card card-md"><div class="card-body"><h2 class="h2 text-center mb-4">Choose a new password</h2><form method="POST" action="{{ route('seller.password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email', $email) }}" class="form-control" required></div><div class="mb-3"><label class="form-label">New password</label><input type="password" name="password" class="form-control" required></div><div class="mb-3"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" required></div><button class="btn btn-success w-100">Reset password</button></form></div></div>
@endsection
