@extends('layouts.app')
@section('content')
<h3>Edit Tarif</h3>

<form method="POST" action="{{ route('admin.tarif.update', $tarif->id_tarif) }}">
    @csrf @method('PUT')
    <div class="mb-3">
        <label>Jenis Kendaraan</label>
        <select name="jenis_kendaraan" class="form-select" required>
            <option value="motor" @selected($tarif->jenis_kendaraan=='motor')>Motor</option>
            <option value="mobil" @selected($tarif->jenis_kendaraan=='mobil')>Mobil</option>
            <option value="lainnya" @selected($tarif->jenis_kendaraan=='lainnya')>Lainnya</option>
        </select>
    </div>
    <div class="mb-3">
        <label>Tarif per Jam</label>
        <input type="number" name="tarif_per_jam" class="form-control" min="0" value="{{ $tarif->tarif_per_jam }}" required>
    </div>
    <button class="btn btn-primary">Update</button>
    <a href="{{ route('admin.tarif.index') }}" class="btn btn-secondary">Batal</a>
</form>
@endsection
