@extends('layouts.admin')

@section('title', 'Dashboard Admin')

@section('content')
<div class="welcome-card">
    <h1>Selamat datang, {{ auth()->user()->name }}</h1>
    <p>Panel Administrasi SI-LPiket</p>
    <p>Gunakan menu <strong>Data Sistem</strong> di sidebar untuk menyiapkan data aplikasi.</p>

    <div class="stats-grid">
        <div class="stat-card info">
            <h3>Total Users</h3>
            <div class="value">{{ $stats['users_baru'] ?? 0 }}</div>
        </div>
        <div class="stat-card success">
            <h3>Siswa</h3>
            <div class="value">{{ $stats['students'] ?? 0 }}</div>
        </div>
        <div class="stat-card warning">
            <h3>Guru</h3>
            <div class="value">{{ $stats['teachers'] ?? 0 }}</div>
        </div>
        <div class="stat-card info">
            <h3>Kelas</h3>
            <div class="value">{{ $stats['classes'] ?? 0 }}</div>
        </div>
        <div class="stat-card warning">
            <h3>Jurusan</h3>
            <div class="value">{{ $stats['majors'] ?? 0 }}</div>
        </div>
        <div class="stat-card danger">
            <h3>Kategori Laporan</h3>
            <div class="value">{{ $stats['categories'] ?? 0 }}</div>
        </div>
        <div class="stat-card success">
            <h3>Jadwal Piket</h3>
            <div class="value">{{ $stats['schedules'] ?? 0 }}</div>
        </div>
    </div>
</div>
@endsection