@extends('layouts.app')
@section('content')
<h3>Dashboard Petugas</h3>
<p>Selamat datang di Sistem Informasi Parkir.</p>
<a href="{{ route('petugas.transaksi.index') }}" class="btn btn-primary">Lihat Transaksi</a>
@endsection
