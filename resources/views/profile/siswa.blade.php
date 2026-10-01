@extends('layouts.siswa')

@section('title', 'Profil Siswa')

@section('content')
@include('profile.form', ['updateRoute' => route('siswa.profile.password')])
@endsection
