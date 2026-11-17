<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\Kendaraan;
use App\Models\Tarif;
use App\Models\AreaParkir;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransaksiController extends Controller
{
    // Daftar kendaraan yang sedang parkir
    public function index()
    {
        $transaksiAktif = Transaksi::with(['kendaraan', 'area'])
            ->where('status', 'masuk')
            ->latest()
            ->get();

        return view('petugas.transaksi.index', compact('transaksiAktif'));
    }

    // Form kendaraan masuk
    public function formMasuk()
    {
        $kendaraans = Kendaraan::orderBy('plat_nomor')->get();
        $tarifs = Tarif::all();
        $areas = AreaParkir::whereColumn('terisi', '<', 'kapasitas')->get();

        return view('petugas.transaksi.form-masuk', compact('kendaraans', 'tarifs', 'areas'));
    }

    // Proses simpan kendaraan masuk
    public function prosesMasuk(Request $request)
    {
        $request->validate([
            'id_kendaraan' => 'required|exists:tb_kendaraan,id_kendaraan',
            'id_tarif' => 'required|exists:tb_tarif,id_tarif',
            'id_area' => 'required|exists:tb_area_parkir,id_area',
        ]);

        // Cegah kendaraan yang masih tercatat parkir dimasukkan lagi
        $sedangParkir = Transaksi::where('id_kendaraan', $request->id_kendaraan)
            ->where('status', 'masuk')
            ->exists();

        if ($sedangParkir) {
            return back()->with('error', 'Kendaraan ini masih tercatat parkir.');
        }

        $error = DB::transaction(function () use ($request) {
            // lockForUpdate supaya dua petugas tidak mengisi slot terakhir bersamaan
            $area = AreaParkir::lockForUpdate()->findOrFail($request->id_area);

            if ($area->terisi >= $area->kapasitas) {
                return 'Area parkir sudah penuh.';
            }

            Transaksi::create([
                'id_kendaraan' => $request->id_kendaraan,
                'waktu_masuk' => now(),
                'id_tarif' => $request->id_tarif,
                'status' => 'masuk',
                'id_user' => auth()->id(),
                'id_area' => $request->id_area,
            ]);

            // Tambah jumlah kendaraan terisi di area tsb
            $area->increment('terisi');

            LogAktivitas::create([
                'id_user' => auth()->id(),
                'aktivitas' => 'Mencatat kendaraan masuk',
                'waktu_aktivitas' => now(),
            ]);

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return redirect()->route('petugas.transaksi.index')->with('success', 'Kendaraan berhasil dicatat masuk.');
    }

    // Form kendaraan keluar (hitung biaya dulu)
    public function formKeluar($id)
    {
        $transaksi = Transaksi::with(['kendaraan', 'tarif', 'area'])->findOrFail($id);

        if ($transaksi->status !== 'masuk') {
            return redirect()->route('petugas.transaksi.index')->with('error', 'Transaksi ini sudah selesai.');
        }

        $waktuMasuk = Carbon::parse($transaksi->waktu_masuk);
        $waktuSekarang = now();

        // Hitung durasi jam, dibulatkan ke atas (minimal 1 jam)
        $durasiJam = max(1, ceil($waktuMasuk->diffInMinutes($waktuSekarang) / 60));
        $biaya = $durasiJam * $transaksi->tarif->tarif_per_jam;

        return view('petugas.transaksi.form-keluar', compact('transaksi', 'durasiJam', 'biaya'));
    }

    // Proses kendaraan keluar
    public function prosesKeluar(Request $request, $id)
    {
        $transaksi = Transaksi::with(['tarif', 'area'])->findOrFail($id);

        if ($transaksi->status !== 'masuk') {
            return redirect()->route('petugas.transaksi.index')->with('error', 'Transaksi ini sudah selesai.');
        }

        $waktuMasuk = Carbon::parse($transaksi->waktu_masuk);
        $waktuKeluar = now();
        $durasiJam = max(1, ceil($waktuMasuk->diffInMinutes($waktuKeluar) / 60));
        $biaya = $durasiJam * $transaksi->tarif->tarif_per_jam;

        DB::transaction(function () use ($transaksi, $waktuKeluar, $durasiJam, $biaya) {
            // Update hanya jika masih berstatus masuk (mencegah proses ganda)
            $updated = Transaksi::where('id_parkir', $transaksi->id_parkir)
                ->where('status', 'masuk')
                ->update([
                    'waktu_keluar' => $waktuKeluar,
                    'durasi_jam' => $durasiJam,
                    'biaya_total' => $biaya,
                    'status' => 'keluar',
                ]);

            if ($updated) {
                // Kurangi jumlah kendaraan terisi di area
                $transaksi->area->decrement('terisi');

                LogAktivitas::create([
                    'id_user' => auth()->id(),
                    'aktivitas' => 'Mencatat kendaraan keluar',
                    'waktu_aktivitas' => now(),
                ]);
            }
        });

        return redirect()->route('petugas.transaksi.struk', $transaksi->id_parkir);
    }

    // Halaman cetak struk
    public function cetakStruk($id)
    {
        $transaksi = Transaksi::with(['kendaraan', 'tarif', 'area', 'petugas'])->findOrFail($id);

        if ($transaksi->status !== 'keluar') {
            return redirect()->route('petugas.transaksi.index')->with('error', 'Struk hanya tersedia setelah kendaraan keluar.');
        }

        return view('petugas.transaksi.struk', compact('transaksi'));
    }
}
