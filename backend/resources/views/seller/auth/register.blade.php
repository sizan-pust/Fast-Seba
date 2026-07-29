@extends('layouts.seller.guest')
@section('title', 'Seller Registration')
@section('content')
<div class="card card-md"><div class="card-body">
<h2 class="h2 text-center mb-1">Create seller account</h2><p class="text-secondary text-center mb-4">Register your business and prepare the catalogue while verification is reviewed.</p>
<form method="POST" action="{{ route('seller.register.store') }}">@csrf
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Owner name</label><input name="name" value="{{ old('name') }}" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Business name</label><input name="business_name" value="{{ old('business_name') }}" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Mobile</label><input name="mobile" value="{{ old('mobile') }}" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">Legal name</label><input name="legal_name" value="{{ old('legal_name') }}" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Initial store name</label><input name="store_name" value="{{ old('store_name') }}" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Trade license number</label><input name="trade_license_number" value="{{ old('trade_license_number') }}" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Tax number</label><input name="tax_number" value="{{ old('tax_number') }}" class="form-control"></div>
<div class="col-md-8"><label class="form-label">Address</label><textarea name="address" class="form-control">{{ old('address') }}</textarea></div>
<div class="col-md-4"><label class="form-label">City</label><input name="city" value="{{ old('city') }}" class="form-control"></div>
</div><div class="form-footer"><button class="btn btn-success w-100">Create seller account</button></div>
</form></div></div><div class="text-center text-secondary mt-3">Already registered? <a href="{{ route('seller.login') }}">Sign in</a></div>
@endsection
