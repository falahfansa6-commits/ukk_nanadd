<?php

namespace Tests\Feature;

use App\Models\AreaParkir;
use App\Models\Kendaraan;
use App\Models\LogAktivitas;
use App\Models\Tarif;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ParkirFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $username, string $password, int $status = 1): User
    {
        return User::create([
            'nama_lengkap' => ucfirst($role),
            'username' => $username,
            'password' => Hash::make($password),
            'role' => $role,
            'status_aktif' => $status,
        ]);
    }

    private function makeData(int $kapasitas = 5): array
    {
        $tarif = Tarif::create(['jenis_kendaraan' => 'motor', 'tarif_per_jam' => 2000]);
        $area = AreaParkir::create(['nama_area' => 'Lantai 1', 'kapasitas' => $kapasitas, 'terisi' => 0]);
        $petugas = $this->makeUser('petugas', 'petugas1', 'secret123');
        $kendaraan = Kendaraan::create([
            'plat_nomor' => 'AB 1234 CD', 'jenis_kendaraan' => 'motor',
            'pemilik' => 'Budi', 'id_user' => $petugas->id_user,
        ]);

        return compact('tarif', 'area', 'petugas', 'kendaraan');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/admin/user')->assertRedirect('/login');
    }

    public function test_each_role_is_redirected_to_its_dashboard(): void
    {
        $this->makeUser('admin', 'admin', 'admin123');
        $this->makeUser('petugas', 'petugas', 'petugas123');
        $this->makeUser('owner', 'owner', 'owner123');

        $this->post('/login', ['username' => 'admin', 'password' => 'admin123'])->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');
        $this->post('/login', ['username' => 'petugas', 'password' => 'petugas123'])->assertRedirect(route('petugas.dashboard'));
        $this->post('/logout');
        $this->post('/login', ['username' => 'owner', 'password' => 'owner123'])->assertRedirect(route('owner.dashboard'));
    }

    public function test_wrong_password_and_inactive_user_cannot_login(): void
    {
        $this->makeUser('petugas', 'petugas', 'petugas123');
        $this->makeUser('petugas', 'nonaktif', 'secret123', 0);

        $this->post('/login', ['username' => 'petugas', 'password' => 'salah'])
            ->assertSessionHas('error', 'Username atau password salah.');
        $this->post('/login', ['username' => 'nonaktif', 'password' => 'secret123'])
            ->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_login_is_logged_in_activity_log(): void
    {
        $this->makeUser('admin', 'admin', 'admin123');
        $this->post('/login', ['username' => 'admin', 'password' => 'admin123']);

        $this->assertDatabaseHas('tb_log_aktivitas', ['aktivitas' => 'Login ke sistem']);
    }

    public function test_role_middleware_blocks_other_roles(): void
    {
        $this->makeUser('petugas', 'petugas', 'petugas123');
        $this->makeUser('owner', 'owner', 'owner123');

        $this->post('/login', ['username' => 'petugas', 'password' => 'petugas123']);
        $this->get('/admin/user')->assertForbidden();
        $this->get('/owner/rekap')->assertForbidden();
        $this->get('/petugas/transaksi')->assertOk();

        $this->post('/logout');
        $this->post('/login', ['username' => 'owner', 'password' => 'owner123']);
        $this->get('/petugas/transaksi')->assertForbidden();
        $this->get('/owner/rekap')->assertOk();
    }

    public function test_masuk_increments_terisi_and_logs(): void
    {
        $d = $this->makeData();
        $this->actingAs($d['petugas'])->post('/petugas/transaksi/masuk', [
            'id_kendaraan' => $d['kendaraan']->id_kendaraan,
            'id_tarif' => $d['tarif']->id_tarif,
            'id_area' => $d['area']->id_area,
        ])->assertRedirect(route('petugas.transaksi.index'));

        $this->assertDatabaseHas('tb_transaksi', ['id_kendaraan' => $d['kendaraan']->id_kendaraan, 'status' => 'masuk']);
        $this->assertEquals(1, $d['area']->fresh()->terisi);
        $this->assertDatabaseHas('tb_log_aktivitas', ['aktivitas' => 'Mencatat kendaraan masuk']);
    }

    public function test_same_vehicle_cannot_enter_twice(): void
    {
        $d = $this->makeData(5);
        $payload = [
            'id_kendaraan' => $d['kendaraan']->id_kendaraan,
            'id_tarif' => $d['tarif']->id_tarif,
            'id_area' => $d['area']->id_area,
        ];

        $this->actingAs($d['petugas'])->post('/petugas/transaksi/masuk', $payload);
        $this->actingAs($d['petugas'])->post('/petugas/transaksi/masuk', $payload)
            ->assertSessionHas('error', 'Kendaraan ini masih tercatat parkir.');

        $this->assertEquals(1, Transaksi::where('status', 'masuk')->count());
        $this->assertEquals(1, $d['area']->fresh()->terisi);
    }

    public function test_full_area_rejects_new_vehicle(): void
    {
        $d = $this->makeData(1);
        $this->actingAs($d['petugas'])->post('/petugas/transaksi/masuk', [
            'id_kendaraan' => $d['kendaraan']->id_kendaraan,
            'id_tarif' => $d['tarif']->id_tarif,
            'id_area' => $d['area']->id_area,
        ]);

        $kendaraan2 = Kendaraan::create([
            'plat_nomor' => 'B 9 X', 'jenis_kendaraan' => 'motor', 'pemilik' => 'Ani', 'id_user' => $d['petugas']->id_user,
        ]);
        $this->actingAs($d['petugas'])->post('/petugas/transaksi/masuk', [
            'id_kendaraan' => $kendaraan2->id_kendaraan,
            'id_tarif' => $d['tarif']->id_tarif,
            'id_area' => $d['area']->id_area,
        ])->assertSessionHas('error', 'Area parkir sudah penuh.');

        $this->assertEquals(1, $d['area']->fresh()->terisi);
    }

    public function test_keluar_calculates_fee_rounded_up_per_hour(): void
    {
        $d = $this->makeData();
        $transaksi = Transaksi::create([
            'id_kendaraan' => $d['kendaraan']->id_kendaraan,
            'waktu_masuk' => now()->subMinutes(150), // 2 jam 30 menit -> dibulatkan 3 jam
            'id_tarif' => $d['tarif']->id_tarif,
            'status' => 'masuk',
            'id_user' => $d['petugas']->id_user,
            'id_area' => $d['area']->id_area,
        ]);
        $d['area']->increment('terisi');

        $this->actingAs($d['petugas'])->get("/petugas/transaksi/keluar/{$transaksi->id_parkir}")
            ->assertOk()->assertSee('Rp6,000');

        $this->actingAs($d['petugas'])->post("/petugas/transaksi/keluar/{$transaksi->id_parkir}")
            ->assertRedirect(route('petugas.transaksi.struk', $transaksi->id_parkir));

        $transaksi->refresh();
        $this->assertEquals('keluar', $transaksi->status);
        $this->assertEquals(3, $transaksi->durasi_jam);
        $this->assertEquals(6000, (int) $transaksi->biaya_total);
        $this->assertEquals(0, $d['area']->fresh()->terisi);
    }

    public function test_minimum_one_hour_charge(): void
    {
        $d = $this->makeData();
        $transaksi = Transaksi::create([
            'id_kendaraan' => $d['kendaraan']->id_kendaraan,
            'waktu_masuk' => now()->subMinutes(5),
            'id_tarif' => $d['tarif']->id_tarif,
            'status' => 'masuk',
            'id_user' => $d['petugas']->id_user,
            'id_area' => $d['area']->id_area,
        ]);
        $d['area']->increment('terisi');

        $this->actingAs($d['petugas'])->post("/petugas/transaksi/keluar/{$transaksi->id_parkir}");
        $this->assertEquals(2000, (int) $transaksi->fresh()->biaya_total);
    }

    public function test_double_keluar_does_not_decrement_twice(): void
    {
        $d = $this->makeData();
        $transaksi = Transaksi::create([
            'id_kendaraan' => $d['kendaraan']->id_kendaraan,
            'waktu_masuk' => now()->subMinutes(30),
            'id_tarif' => $d['tarif']->id_tarif,
            'status' => 'masuk',
            'id_user' => $d['petugas']->id_user,
            'id_area' => $d['area']->id_area,
        ]);
        $d['area']->update(['terisi' => 2]); // dua kendaraan terisi

        $this->actingAs($d['petugas'])->post("/petugas/transaksi/keluar/{$transaksi->id_parkir}");
        $this->actingAs($d['petugas'])->post("/petugas/transaksi/keluar/{$transaksi->id_parkir}")
            ->assertSessionHas('error');

        $this->assertEquals(1, $d['area']->fresh()->terisi);
    }

    public function test_struk_only_available_after_keluar(): void
    {
        $d = $this->makeData();
        $transaksi = Transaksi::create([
            'id_kendaraan' => $d['kendaraan']->id_kendaraan,
            'waktu_masuk' => now(),
            'id_tarif' => $d['tarif']->id_tarif,
            'status' => 'masuk',
            'id_user' => $d['petugas']->id_user,
            'id_area' => $d['area']->id_area,
        ]);

        $this->actingAs($d['petugas'])->get("/petugas/struk/{$transaksi->id_parkir}")
            ->assertRedirect(route('petugas.transaksi.index'));
    }

    public function test_owner_rekap_sums_income_within_date_range(): void
    {
        $d = $this->makeData();
        foreach ([[2000, '2026-11-01 10:00:00'], [4000, '2026-11-03 10:00:00'], [9000, '2026-12-01 10:00:00']] as [$biaya, $waktu]) {
            Transaksi::create([
                'id_kendaraan' => $d['kendaraan']->id_kendaraan,
                'waktu_masuk' => $waktu, 'waktu_keluar' => $waktu,
                'id_tarif' => $d['tarif']->id_tarif, 'durasi_jam' => 1,
                'biaya_total' => $biaya, 'status' => 'keluar',
                'id_user' => $d['petugas']->id_user, 'id_area' => $d['area']->id_area,
            ]);
        }
        $owner = $this->makeUser('owner', 'owner', 'owner123');

        $this->actingAs($owner)->get('/owner/rekap')->assertSee('Rp15,000');
        $this->actingAs($owner)->get('/owner/rekap?dari=2026-11-01&sampai=2026-11-30')
            ->assertSee('Rp6,000');
    }

    public function test_admin_cannot_delete_or_demote_own_account(): void
    {
        $admin = $this->makeUser('admin', 'admin', 'admin123');

        $this->actingAs($admin)->delete("/admin/user/{$admin->id_user}")
            ->assertSessionHas('error');
        $this->assertDatabaseHas('tb_user', ['id_user' => $admin->id_user]);

        $this->actingAs($admin)->put("/admin/user/{$admin->id_user}", [
            'nama_lengkap' => 'Admin', 'username' => 'admin', 'role' => 'petugas', 'status_aktif' => 1,
        ])->assertSessionHas('error');
        $this->assertEquals('admin', $admin->fresh()->role);
    }

    public function test_deleting_data_in_use_shows_error_instead_of_crash(): void
    {
        $d = $this->makeData();
        Transaksi::create([
            'id_kendaraan' => $d['kendaraan']->id_kendaraan,
            'waktu_masuk' => now(), 'id_tarif' => $d['tarif']->id_tarif,
            'status' => 'masuk', 'id_user' => $d['petugas']->id_user, 'id_area' => $d['area']->id_area,
        ]);
        $admin = $this->makeUser('admin', 'admin', 'admin123');

        $this->actingAs($admin)->delete("/admin/kendaraan/{$d['kendaraan']->id_kendaraan}")->assertSessionHas('error');
        $this->actingAs($admin)->delete("/admin/tarif/{$d['tarif']->id_tarif}")->assertSessionHas('error');
        $this->actingAs($admin)->delete("/admin/area/{$d['area']->id_area}")->assertSessionHas('error');

        $this->assertDatabaseHas('tb_kendaraan', ['id_kendaraan' => $d['kendaraan']->id_kendaraan]);
    }

    public function test_capacity_cannot_drop_below_terisi(): void
    {
        $d = $this->makeData(5);
        $d['area']->update(['terisi' => 3]);
        $admin = $this->makeUser('admin', 'admin', 'admin123');

        $this->actingAs($admin)->put("/admin/area/{$d['area']->id_area}", [
            'nama_area' => 'Lantai 1', 'kapasitas' => 2,
        ])->assertSessionHas('error');

        $this->assertEquals(5, $d['area']->fresh()->kapasitas);
    }

    public function test_admin_can_view_log_page(): void
    {
        $admin = $this->makeUser('admin', 'admin', 'admin123');
        LogAktivitas::create(['id_user' => $admin->id_user, 'aktivitas' => 'Contoh', 'waktu_aktivitas' => now()]);

        $this->actingAs($admin)->get('/admin/log-aktivitas')->assertOk()->assertSee('Contoh');
    }
}
