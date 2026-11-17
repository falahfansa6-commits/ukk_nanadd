@extends('layouts.app')
@section('content')
<h3>Dashboard Owner</h3>
<p>Selamat datang di Sistem Informasi Parkir.</p>
<a href="{{ route('owner.rekap.index') }}" class="btn btn-primary">Lihat Rekap Transaksi</a>
@endsection
