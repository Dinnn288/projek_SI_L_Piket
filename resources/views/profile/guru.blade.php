@extends('layouts.guru')

@section('title', 'Profil Guru')

@section('content')
@include('profile.form', ['updateRoute' => route('guru.profile.password')])
@endsection
