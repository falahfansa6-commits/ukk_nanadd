<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AreaParkir;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class AreaParkirController extends Controller
{
    public function index()
    {
        $areas = AreaParkir::latest()->paginate(10);
        return view('admin.area.index', compact('areas'));
    }

    public function create()
    {
        return view('admin.area.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_area' => 'required|string|max:50',
            'kapasitas' => 'required|integer|min:1',
        ]);

        AreaParkir::create($request->only('nama_area', 'kapasitas'));

        return redirect()->route('admin.area.index')->with('success', 'Area parkir berhasil ditambahkan.');
    }

    public function edit(AreaParkir $area)
    {
        return view('admin.area.edit', compact('area'));
    }

    public function update(Request $request, AreaParkir $area)
    {
        $request->validate([
            'nama_area' => 'required|string|max:50',
            'kapasitas' => 'required|integer|min:1',
        ]);

        // Kapasitas tidak boleh lebih kecil dari jumlah kendaraan yang sedang terisi
        if ((int) $request->kapasitas < $area->terisi) {
            return back()->withInput()->with('error', 'Kapasitas tidak boleh lebih kecil dari jumlah kendaraan terisi (' . $area->terisi . ').');
        }

        // Kolom terisi tidak diubah dari form, hanya lewat modul transaksi
        $area->update($request->only('nama_area', 'kapasitas'));

        return redirect()->route('admin.area.index')->with('success', 'Area parkir berhasil diperbarui.');
    }

    public function destroy(AreaParkir $area)
    {
        try {
            $area->delete();
        } catch (QueryException $e) {
            return back()->with('error', 'Data tidak bisa dihapus karena sudah dipakai di data lain (transaksi/log).');
        }

        return redirect()->route('admin.area.index')->with('success', 'Area parkir berhasil dihapus.');
    }
}
