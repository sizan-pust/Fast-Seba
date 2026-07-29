@extends('layouts.admin.app')
@section('title', $title)
@section('content')
@php
    $params = array_filter(['id' => $isEdit ? $model->getKey() : null, 'view' => $view ?: null]);
    $action = $isEdit
        ? route('admin.system.'.$module.'.update', $params)
        : route('admin.system.'.$module.'.store', array_filter(['view' => $view ?: null]));
@endphp
<div class="page-header d-print-none"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">System administration</div><h2 class="page-title">{{ $title }}</h2></div><div class="col-auto ms-auto"><a href="{{ route('admin.system.'.$module.'.index', array_filter(['view' => $view ?: null])) }}" class="btn btn-outline-secondary">Back to list</a></div></div></div>
<form method="POST" action="{{ $action }}" class="card">
@csrf
@if ($isEdit) @method('PUT') @endif
@if ($view)<input type="hidden" name="view" value="{{ $view }}">@endif
<div class="card-body">
@if ($module === 'roles-users' && $view === 'roles')
<div class="mb-3"><label class="form-label required">Role name</label><input type="text" name="name" value="{{ old('name', $model->name) }}" class="form-control" required @disabled($model->name === 'Super Admin')>@if ($model->name === 'Super Admin')<input type="hidden" name="name" value="Super Admin">@endif</div>
@php $selectedPermissions = collect(old('permission_ids', $model->exists ? $model->permissions->pluck('id')->all() : []))->map(fn ($id) => (string) $id); @endphp
<div class="row">@foreach ($permissions->groupBy(fn ($permission) => str($permission->name)->before('.')->toString()) as $group => $items)<div class="col-md-6 col-xl-4 mb-3"><div class="card card-sm h-100"><div class="card-header"><h3 class="card-title">{{ str($group)->replace('_', ' ')->title() }}</h3></div><div class="card-body">@foreach ($items as $permission)<label class="form-check mb-2"><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" class="form-check-input" @checked($selectedPermissions->contains((string) $permission->id))><span class="form-check-label">{{ $permission->name }}</span></label>@endforeach</div></div></div>@endforeach</div>
@elseif ($module === 'roles-users')
<div class="row">
<div class="col-md-6 mb-3"><label class="form-label required">Name</label><input type="text" name="name" value="{{ old('name', $model->name) }}" class="form-control" required></div>
<div class="col-md-6 mb-3"><label class="form-label required">Email</label><input type="email" name="email" value="{{ old('email', $model->email) }}" class="form-control" required></div>
<div class="col-md-6 mb-3"><label class="form-label">Mobile</label><input type="text" name="mobile" value="{{ old('mobile', $model->mobile) }}" class="form-control"></div>
<div class="col-md-6 mb-3"><label class="form-label required">Status</label><select name="status" class="form-select" required>@foreach (['active','inactive','blocked'] as $status)<option value="{{ $status }}" @selected(old('status', $model->status ?: 'active') === $status)>{{ str($status)->title() }}</option>@endforeach</select></div>
<div class="col-md-6 mb-3"><label class="form-label">{{ $isEdit ? 'New password' : 'Password' }}</label><input type="password" name="password" class="form-control" @required(! $isEdit)></div>
<div class="col-md-6 mb-3"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" @required(! $isEdit)></div>
<div class="col-12"><label class="form-label">Admin roles</label>@php $selectedRoles = collect(old('role_ids', $model->exists ? $model->roles->pluck('id')->all() : []))->map(fn ($id) => (string) $id); @endphp<select name="role_ids[]" class="form-select" multiple size="8">@foreach ($roles as $role)<option value="{{ $role->id }}" @selected($selectedRoles->contains((string) $role->id))>{{ $role->name }}</option>@endforeach</select></div>
</div>
@elseif ($module === 'settings')
<div class="mb-3"><label class="form-label required">Variable</label><input type="text" name="variable" value="{{ old('variable', $model->variable) }}" class="form-control" required></div>
<div class="mb-3"><label class="form-label required">JSON value</label><textarea name="value_json" class="form-control font-monospace" rows="22" required>{{ old('value_json', json_encode($model->value ?: [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) }}</textarea></div>
@elseif ($module === 'system-operations')
<div class="row"><div class="col-md-6 mb-3"><label class="form-label required">Version</label><input type="text" name="version" value="{{ old('version', $model->version) }}" class="form-control" required></div><div class="col-md-6 mb-3"><label class="form-label required">Status</label><select name="status" class="form-select" required>@foreach (['planned','staged','deployed','failed','rolled_back'] as $status)<option value="{{ $status }}" @selected(old('status', $model->status ?: 'planned') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></div><div class="col-12 mb-3"><label class="form-label">Checksum</label><input type="text" name="checksum" value="{{ old('checksum', $model->checksum) }}" class="form-control font-monospace"></div><div class="col-12 mb-3"><label class="form-label">Release notes</label><textarea name="release_notes" class="form-control" rows="10">{{ old('release_notes', $model->release_notes) }}</textarea></div></div>
@endif
</div><div class="card-footer text-end"><button type="submit" class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Create record' }}</button></div>
</form>
@endsection
