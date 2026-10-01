@extends('layouts.admin')

@section('title', 'Profil Admin')

@section('content')
@include('profile.form', ['updateRoute' => route('admin.profile.password')])
@endsection
