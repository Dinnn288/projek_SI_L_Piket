@extends('layouts.kepala-sekolah')

@section('title', 'Profil Kepala Sekolah')

@section('content')
@include('profile.form', ['updateRoute' => route('kepala-sekolah.profile.password')])
@endsection
