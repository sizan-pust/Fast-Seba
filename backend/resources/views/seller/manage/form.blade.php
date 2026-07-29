@extends('layouts.seller.app')
@section('title', $title)
@section('content')
@php($query=$query??[])
<div class="page-header mb-3"><div class="row align-items-center"><div class="col"><div class="page-pretitle">Seller Operations</div><h2 class="page-title">{{ $title }}</h2></div><div class="col-auto"><a href="{{ route('seller.resource.'.$module.'.index',$query) }}" class="btn btn-outline-secondary">Back</a></div></div></div>
<form method="POST" action="{{ $isEdit ? route('seller.resource.'.$module.'.update',array_merge(['id'=>$recordId],$query)) : route('seller.resource.'.$module.'.store',$query) }}" enctype="multipart/form-data" class="card">@csrf @if($isEdit)@method('PUT')@endif
<div class="card-body"><div class="row g-3">@foreach($fields as $field)<div class="{{ ($field['type']??'')==='hidden'?'d-none':((($field['type']??'')==='textarea'||($field['type']??'')==='multiselect')?'col-12':'col-md-6') }}">@include('seller.manage._field',['field'=>$field])</div>@endforeach</div></div><div class="card-footer text-end"><button class="btn btn-success">{{ $isEdit?'Save changes':'Create record' }}</button></div></form>
@endsection
