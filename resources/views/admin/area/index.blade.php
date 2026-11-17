@extends('layouts.app')
@section('content')
<h3>Area Parkir</h3>
<a href="{{ route('admin.area.create') }}" class="btn btn-primary mb-3">+ Tambah Area</a>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>#</th>
            <th>Nama Area</th>
            <th>Kapasitas</th>
            <th>Terisi</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        @foreach($areas as $i => $area)
        <tr>
            <td>{{ $areas->firstItem() + $i }}</td>
            <td>{{ $area->nama_area }}</td>
            <td>{{ $area->kapasitas }}</td>
            <td>{{ $area->terisi }}</td>
            <td>
                <a href="{{ route('admin.area.edit', $area->id_area) }}" class="btn btn-sm btn-warning">Edit</a>
                <form action="{{ route('admin.area.destroy', $area->id_area) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Yakin hapus area ini?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Hapus</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

{{ $areas->links() }}
@endsection
