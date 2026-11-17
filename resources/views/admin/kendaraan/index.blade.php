@extends('layouts.app')
@section('content')
<h3>Data Kendaraan</h3>
<a href="{{ route('admin.kendaraan.create') }}" class="btn btn-primary mb-3">+ Tambah Kendaraan</a>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>#</th>
            <th>Plat Nomor</th>
            <th>Jenis</th>
            <th>Warna</th>
            <th>Pemilik</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        @foreach($kendaraans as $i => $k)
        <tr>
            <td>{{ $kendaraans->firstItem() + $i }}</td>
            <td>{{ $k->plat_nomor }}</td>
            <td>{{ $k->jenis_kendaraan }}</td>
            <td>{{ $k->warna ?? '-' }}</td>
            <td>{{ $k->pemilik }}</td>
            <td>
                <a href="{{ route('admin.kendaraan.edit', $k->id_kendaraan) }}" class="btn btn-sm btn-warning">Edit</a>
                <form action="{{ route('admin.kendaraan.destroy', $k->id_kendaraan) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Yakin hapus kendaraan ini?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Hapus</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

{{ $kendaraans->links() }}
@endsection
