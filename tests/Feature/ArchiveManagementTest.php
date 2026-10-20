<?php

namespace Tests\Feature;

use App\Exceptions\LdapUnavailableException;
use App\Http\Controllers\ActivityHistoryController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\ReportController;
use App\Models\User;
use App\Services\LdapAuthenticator;
use App\Support\ArchiveAccess;
use App\Support\DuplicateCandidateDiscovery;
use App\Support\RolePermissions;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveManagementTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();

        $this->admin = User::query()->create([
            'nama' => 'Admin',
            'username' => 'superadmin',
            'password' => bcrypt('password'),
            'role' => 'Admin',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        DB::table('kategori_arsip')->insert([
            'id' => 1,
            'nama_kategori' => 'Keuangan',
            'status' => 'Aktif',
        ]);
        DB::table('lokasi')->insert([
            'id' => 1,
            'gudang' => 'Gudang Utama',
            'rak' => 'Rak A',
            'lemari' => '01',
            'box' => '01',
            'status' => 'Aktif',
        ]);
    }

    public function test_login_form_posts_to_same_origin_with_csrf_token(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<form method="post" action="/login">', false)
            ->assertSee('name="_token"', false)
            ->assertSee('class="auth-layout"', false)
            ->assertSee('data-theme-toggle', false);
    }

    public function test_local_login_remains_available_in_local_authentication_mode(): void
    {
        config(['ldap.auth_mode' => 'local']);

        $this->post(route('login.store'), [
            'username' => 'superadmin',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertSame('local', session('auth_method'));
    }

    public function test_active_pegawai_can_log_in_with_their_own_password(): void
    {
        config(['ldap.auth_mode' => 'local']);
        $pegawai = User::query()->create([
            'nama' => 'Pegawai Aktif',
            'username' => 'pegawai-login',
            'password' => Hash::make('pegawai-secure-password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        $this->withSession([
            'auth_method' => 'ldap',
            'ldap_profile' => ['username' => 'stale-account'],
            'old_private_value' => 'must be cleared',
        ]);
        $previousSessionId = session()->getId();
        $this->post(route('login.store'), [
            'username' => '  pegawai-login  ',
            'password' => 'pegawai-secure-password',
        ])->assertRedirect(route('pegawai.dashboard'));

        $this->assertAuthenticatedAs($pegawai);
        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertNull(session('old_private_value'));
        $this->assertNull(session('ldap_profile'));
        $this->assertSame('local', session('auth_method'));
        $this->assertSame('Pegawai', $pegawai->fresh()->role);
        $this->assertSame('Keuangan', $pegawai->fresh()->division);
    }

    public function test_login_accepts_petugas_and_rejects_unmapped_legacy_roles(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['ldap.auth_mode' => 'local']);
        $accounts = [
            ['Petugas Arsip', 'petugas-login', 'dashboard'],
        ];

        foreach ($accounts as [$role, $username, $routeName]) {
            $user = User::query()->create([
                'nama' => $role,
                'username' => $username,
                'password' => Hash::make('role-specific-password'),
                'role' => $role,
                'status' => 'Aktif',
                'division' => 'Keuangan',
            ]);

            $this->post(route('login.store'), [
                'username' => $username,
                'password' => 'role-specific-password',
            ])->assertRedirect(route($routeName));
            $this->assertAuthenticatedAs($user);

            $this->post(route('logout'))->assertRedirect(route('login'));
            $this->assertGuest();
            $this->assertNull(session('auth_method'));
            $this->assertNull(session('ldap_profile'));
        }

        foreach ([
            'Super Admin',
            'Customer',
            'Pengguna Umum',
            'Petugas Gudang',
            'Admin Unit/Petugas Arsip',
            'Verifikator',
            'Pimpinan/Approver',
            'Kepala Divisi',
        ] as $legacyRole) {
            $user = User::query()->create([
                'nama' => 'Role '.$legacyRole,
                'username' => 'legacy-'.strtolower(str_replace(' ', '-', $legacyRole)),
                'password' => Hash::make('role-specific-password'),
                'role' => $legacyRole,
                'status' => 'Aktif',
                'division' => 'Keuangan',
            ]);

            $this->from(route('login'))->post(route('login.store'), [
                'username' => $user->username,
                'password' => 'role-specific-password',
            ])->assertRedirect(route('login'))->assertSessionHasErrors('username');
            $this->assertGuest();
        }
    }

    public function test_customer_account_routes_are_removed_and_admin_form_exposes_only_staff_roles(): void
    {
        foreach ([
            'customer.portal',
            'customer.archives.index',
            'customer.submissions.create',
            'customer.submissions.store',
            'customer.access-requests.create',
            'customer.access-requests.store',
            'customer-reviews.index',
        ] as $route) {
            $this->assertFalse(Route::has($route), $route.' must not be an internal account route.');
        }

        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('<option value="Petugas Arsip"', false)
            ->assertSee('<option value="Pegawai"', false)
            ->assertDontSee('<option value="Admin"', false)
            ->assertDontSee('<option value="Super Admin"', false)
            ->assertDontSee('<option value="Customer"', false);
    }

    public function test_local_login_rejects_an_inactive_account_and_an_invalid_password(): void
    {
        config(['ldap.auth_mode' => 'local']);
        $pegawai = User::query()->create([
            'nama' => 'Pegawai Nonaktif',
            'username' => 'pegawai-nonaktif',
            'password' => Hash::make('pegawai-secure-password'),
            'role' => 'Pegawai',
            'status' => 'Nonaktif',
            'division' => 'Keuangan',
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'username' => $pegawai->username,
            'password' => 'pegawai-secure-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->from(route('login'))->post(route('login.store'), [
            'username' => 'superadmin',
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_ldap_login_uses_directory_identity_and_preserves_local_permissions(): void
    {
        config(['ldap.auth_mode' => 'ldap']);
        $pegawai = User::query()->create([
            'nama' => 'Pegawai Arsip',
            'username' => 'pegawai01',
            'password' => bcrypt('local-password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $ldap = \Mockery::mock(LdapAuthenticator::class);
        $ldap->shouldReceive('authenticate')
            ->once()
            ->with('pegawai01', 'directory-password')
            ->andReturn([
                'username' => 'pegawai01',
                'name' => 'Directory Name',
                'email' => 'directory@example.org',
            ]);
        $this->app->instance(LdapAuthenticator::class, $ldap);

        $this->post(route('login.store'), [
            'username' => 'pegawai01',
            'password' => 'directory-password',
        ])->assertRedirect(route('pegawai.dashboard'));

        $this->assertAuthenticatedAs($pegawai);
        $this->assertSame('Pegawai', $pegawai->fresh()->role);
        $this->assertSame('Keuangan', $pegawai->fresh()->division);
        $this->assertSame('ldap', session('auth_method'));
        $this->assertSame('directory@example.org', session('ldap_profile.email'));
    }

    public function test_ldap_failure_does_not_fall_back_to_a_valid_local_password(): void
    {
        config(['ldap.auth_mode' => 'ldap']);
        $ldap = \Mockery::mock(LdapAuthenticator::class);
        $ldap->shouldReceive('authenticate')->once()->andReturnNull();
        $this->app->instance(LdapAuthenticator::class, $ldap);

        $this->post(route('login.store'), [
            'username' => 'superadmin',
            'password' => 'password',
        ])->assertSessionHasErrors(['username' => 'Username atau password tidak sesuai.']);

        $this->assertGuest();
    }

    public function test_ldap_login_does_not_create_an_unprovisioned_application_account(): void
    {
        config(['ldap.auth_mode' => 'ldap']);
        $ldap = \Mockery::mock(LdapAuthenticator::class);
        $ldap->shouldReceive('authenticate')
            ->once()
            ->andReturn([
                'username' => 'new-user',
                'name' => 'New User',
                'email' => null,
            ]);
        $this->app->instance(LdapAuthenticator::class, $ldap);

        $this->post(route('login.store'), [
            'username' => 'new-user',
            'password' => 'directory-password',
        ])->assertSessionHasErrors(['username' => 'Akun direktori belum diberi akses ke aplikasi. Hubungi administrator.']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_ldap_login_rejects_an_inactive_local_account(): void
    {
        config(['ldap.auth_mode' => 'ldap']);
        $this->admin->update(['status' => 'Nonaktif']);
        $ldap = \Mockery::mock(LdapAuthenticator::class);
        $ldap->shouldReceive('authenticate')
            ->once()
            ->andReturn([
                'username' => 'superadmin',
                'name' => 'Admin',
                'email' => null,
            ]);
        $this->app->instance(LdapAuthenticator::class, $ldap);

        $this->post(route('login.store'), [
            'username' => 'superadmin',
            'password' => 'directory-password',
        ])->assertSessionHasErrors(['username' => 'Akun direktori belum diberi akses ke aplikasi. Hubungi administrator.']);

        $this->assertGuest();
    }

    public function test_ldap_directory_failures_show_safe_message_without_internal_details(): void
    {
        config(['ldap.auth_mode' => 'ldap']);
        $ldap = \Mockery::mock(LdapAuthenticator::class);
        $ldap->shouldReceive('authenticate')
            ->once()
            ->andThrow(new LdapUnavailableException('private server details'));
        $this->app->instance(LdapAuthenticator::class, $ldap);

        $this->from(route('login'))->post(route('login.store'), [
            'username' => 'superadmin',
            'password' => 'directory-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => 'Autentikasi direktori sedang tidak tersedia. Coba lagi atau hubungi administrator.',
            ]);

        $this->assertGuest();
    }

    public function test_ldap_service_fails_closed_when_the_php_extension_is_unavailable(): void
    {
        if (extension_loaded('ldap')) {
            $this->markTestSkipped('This test covers PHP installations without the LDAP extension.');
        }

        $this->expectException(LdapUnavailableException::class);

        app(LdapAuthenticator::class)->authenticate('pegawai01', 'directory-password');
    }

    public function test_account_profile_displays_ldap_identity_from_the_authenticated_session(): void
    {
        $this->actingAs($this->admin)->withSession([
            'auth_method' => 'ldap',
            'ldap_profile' => [
                'username' => 'superadmin',
                'name' => 'Directory Name',
                'email' => 'directory@example.org',
            ],
        ])->get(route('account.show'))
            ->assertOk()
            ->assertSee('Directory Name')
            ->assertSee('directory@example.org')
            ->assertSee('LDAP')
            ->assertDontSee('action="/akun/password"', false);
    }

    public function test_ldap_accounts_cannot_change_their_local_password(): void
    {
        config(['ldap.auth_mode' => 'ldap']);

        $this->actingAs($this->admin)
            ->put(route('account.password'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('password', $this->admin->fresh()->password));
    }

    public function test_super_admin_can_create_archive_and_get_a_unique_qr_and_audit_entry(): void
    {
        $response = $this->actingAs($this->admin)->post(route('archives.store'), $this->archiveData());

        $response->assertRedirect(route('archives.show', 1));
        $this->assertDatabaseHas('arsip', [
            'id' => 1,
            'nomor_berkas' => '001/KEU/2026',
            'tahun' => 2026,
            'bulan' => 4,
            'qr_code' => 'ARSIP-2026-04-000001',
            'created_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => 1,
            'aktivitas' => 'Menambahkan arsip',
            'user_id' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('qr_batches', [
            'tahun' => 2026,
            'bulan' => 4,
            'jumlah_qr' => 1,
        ]);
    }

    public function test_archive_can_be_created_without_a_digital_attachment(): void
    {
        $this->actingAs($this->admin)
            ->post(route('archives.store'), $this->archiveData())
            ->assertRedirect(route('archives.show', 1));

        $this->assertDatabaseHas('arsip', [
            'id' => 1,
            'qr_code' => 'ARSIP-2026-04-000001',
        ]);
        $this->assertDatabaseCount('arsip_files', 0);

        $this->actingAs($this->admin)
            ->get(route('archives.show', 1))
            ->assertOk()
            ->assertSee('Tidak ada lampiran digital.');
    }

    public function test_archive_can_be_created_with_one_digital_attachment(): void
    {
        Storage::fake('legacy_uploads');

        $this->actingAs($this->admin)
            ->post(route('archives.store'), $this->archiveData([
                'files' => [UploadedFile::fake()->create('laporan.pdf', 20, 'application/pdf')],
            ]))
            ->assertRedirect(route('archives.show', 1));

        $this->assertDatabaseCount('arsip', 1);
        $this->assertDatabaseHas('arsip_files', [
            'arsip_id' => 1,
            'nama_file' => 'laporan.pdf',
        ]);
        $this->assertCount(1, Storage::disk('legacy_uploads')->allFiles('arsip'));
    }

    public function test_archive_can_be_created_with_multiple_digital_attachments(): void
    {
        Storage::fake('legacy_uploads');

        $this->actingAs($this->admin)
            ->post(route('archives.store'), $this->archiveData([
                'files' => [
                    UploadedFile::fake()->create('laporan.pdf', 20, 'application/pdf'),
                    UploadedFile::fake()->create('lembar-kerja.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                    UploadedFile::fake()->create('foto.png', 20, 'image/png'),
                ],
            ]))
            ->assertRedirect(route('archives.show', 1));

        $this->assertDatabaseCount('arsip', 1);
        $this->assertDatabaseCount('arsip_files', 3);
        $this->assertSame(
            [1],
            DB::table('arsip_files')->distinct()->pluck('arsip_id')->all()
        );
        $this->assertDatabaseHas('arsip', [
            'id' => 1,
            'qr_code' => 'ARSIP-2026-04-000001',
        ]);
        $this->assertCount(3, Storage::disk('legacy_uploads')->allFiles('arsip'));
    }

    public function test_confidential_archive_attachments_remain_protected_from_direct_file_urls(): void
    {
        Storage::fake('legacy_uploads');
        $archiveId = $this->insertArchive([
            'id' => 1,
            'classification' => 'Dokumen Rahasia',
        ]);
        $path = 'arsip/0123456789abcdef01234567.pdf';
        Storage::disk('legacy_uploads')->put($path, 'confidential');
        $fileId = DB::table('arsip_files')->insertGetId([
            'arsip_id' => $archiveId,
            'nama_file' => 'rahasia.pdf',
            'file_path' => 'uploads/'.$path,
            'tipe_file' => 'application/pdf',
            'uploaded_by' => $this->admin->id,
            'created_at' => now(),
        ]);
        $staff = User::query()->create([
            'nama' => 'Pegawai',
            'username' => 'pegawai',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        $this->actingAs($staff)
            ->get(route('archives.files.show', $fileId))
            ->assertForbidden();
    }

    public function test_editing_archive_preserves_its_qr_id(): void
    {
        $this->insertArchive([
            'id' => 1,
            'qr_code' => 'ARSIP-2026-04-000007',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('archives.update', 1), $this->archiveData([
                'nomor_berkas' => '002/KEU/2026',
                'nama_berkas' => 'Laporan baru',
            ]));

        $response->assertRedirect(route('archives.show', 1));
        $this->assertDatabaseHas('arsip', [
            'id' => 1,
            'nomor_berkas' => '002/KEU/2026',
            'nama_berkas' => 'Laporan baru',
            'qr_code' => 'ARSIP-2026-04-000007',
        ]);
        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => 1,
            'aktivitas' => 'Mengubah arsip',
        ]);
    }

    public function test_moving_archive_records_origin_and_destination_in_activity_history(): void
    {
        DB::table('lokasi')->insert([
            'id' => 2,
            'gudang' => 'Gudang Timur',
            'rak' => 'Rak B',
            'lemari' => '02',
            'box' => '05',
            'status' => 'Aktif',
        ]);
        $this->insertArchive(['id' => 1]);

        $this->actingAs($this->admin)
            ->put(route('archives.update', 1), $this->archiveData(['lokasi_id' => 2]))
            ->assertRedirect(route('archives.show', 1));

        $this->assertDatabaseHas('arsip', ['id' => 1, 'lokasi_id' => 2]);
        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => 1,
            'user_id' => $this->admin->id,
            'aktivitas' => 'Mengubah arsip',
            'keterangan' => 'Perubahan data arsip: Laporan Keuangan → Laporan Keuangan; Pemindahan lokasi: Gudang Utama / Rak A / 01 / 01 → Gudang Timur / Rak B / 02 / 05',
        ]);
    }

    public function test_staff_only_sees_archives_allowed_for_their_division(): void
    {
        $staff = User::query()->create([
            'nama' => 'Petugas',
            'username' => 'petugas',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip publik',
            'classification' => 'Publik',
            'division' => 'Keuangan',
        ]);
        $this->insertArchive(['id' => 2, 'nama_berkas' => 'Arsip divisi', 'classification' => 'Internal Divisi', 'division' => 'Keuangan']);
        $this->insertArchive(['id' => 3, 'nama_berkas' => 'Arsip divisi lain', 'classification' => 'Internal Divisi', 'division' => 'Pengadaan']);
        $this->insertArchive(['id' => 4, 'nama_berkas' => 'Arsip rahasia', 'classification' => 'Dokumen Rahasia']);

        $this->actingAs($staff)
            ->get(route('archives.index'))
            ->assertOk()
            ->assertSee('id="archives-app"', false)
            ->assertSee('id="archives-props"', false)
            ->assertSee('Arsip publik')
            ->assertSee('Arsip divisi')
            ->assertDontSee('Arsip divisi lain')
            ->assertDontSee('Arsip rahasia');
    }

    public function test_archive_search_matches_division_warehouse_box_and_category_like_legacy(): void
    {
        DB::table('kategori_arsip')->insert([
            'id' => 2,
            'nama_kategori' => 'Kategori Penelitian',
            'status' => 'Aktif',
        ]);
        DB::table('lokasi')->insert([
            'id' => 2,
            'gudang' => 'Gudang Penelitian',
            'rak' => 'Rak B',
            'lemari' => '02',
            'box' => 'Box Khusus',
            'status' => 'Aktif',
        ]);
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip target pencarian',
            'kategori_id' => 2,
            'lokasi_id' => 2,
            'division' => 'Divisi Penelitian',
        ]);
        $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Arsip lain',
            'division' => 'Divisi Keuangan',
        ]);

        foreach (['Divisi Penelitian', 'Gudang Penelitian', 'Box Khusus', 'Kategori Penelitian'] as $term) {
            $this->actingAs($this->admin)
                ->get(route('archives.index', ['q' => $term]))
                ->assertOk()
                ->assertSee('Arsip target pencarian')
                ->assertDontSee('Arsip lain');
        }
    }

    public function test_staff_cannot_create_or_edit_a_confidential_archive(): void
    {
        $staff = User::query()->create([
            'nama' => 'Petugas',
            'username' => 'petugas',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        $this->actingAs($staff)
            ->get(route('archives.create'))
            ->assertOk();

        $this->actingAs($staff)
            ->post(route('archives.store'), $this->archiveData([
                'classification' => 'Dokumen Rahasia',
                'division' => '',
            ]))
            ->assertForbidden();
    }

    public function test_non_manager_cannot_create_or_update_archives(): void
    {
        $customer = User::query()->create([
            'nama' => 'Pengguna',
            'username' => 'pengguna',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => null,
        ]);
        $archiveId = $this->insertArchive(['id' => 1, 'classification' => 'Publik']);

        $this->actingAs($customer)
            ->get(route('archives.create'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('archives.edit', $archiveId))
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('archives.store'), $this->archiveData())
            ->assertForbidden();

        $this->actingAs($customer)
            ->put(route('archives.update', $archiveId), $this->archiveData())
            ->assertForbidden();

        $this->assertDatabaseHas('arsip', [
            'id' => $archiveId,
            'nama_berkas' => 'Laporan Keuangan',
        ]);
    }

    public function test_roles_without_legacy_listing_access_cannot_browse_archive_list(): void
    {
        $verifier = User::query()->create([
            'nama' => 'Verifikator',
            'username' => 'verifikator',
            'password' => bcrypt('password'),
            'role' => 'Verifikator',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        $this->actingAs($verifier)
            ->get(route('archives.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
    }

    public function test_three_internal_roles_keep_distinct_division_and_admin_access(): void
    {
        $staff = User::query()->create([
            'nama' => 'Petugas Arsip',
            'username' => 'petugas-operasional',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $employee = User::query()->create([
            'nama' => 'Pegawai Operasional',
            'username' => 'pegawai-operasional',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $ownArchive = $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Operasional Keuangan',
            'classification' => 'Publik',
            'division' => ' keuangan ',
        ]);
        $otherArchive = $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Arsip Operasional Pengadaan',
            'classification' => 'Publik',
            'division' => 'Pengadaan',
        ]);
        DB::table('lokasi')->insert([
            'id' => 2,
            'gudang' => 'Gudang Pengadaan',
            'rak' => 'Rak Z',
            'lemari' => '02',
            'box' => '02',
            'status' => 'Aktif',
        ]);
        DB::table('arsip')->where('id', $otherArchive)->update(['lokasi_id' => 2]);

        $this->actingAs($staff)
            ->get(route('archives.index'))
            ->assertOk()
            ->assertSee('Arsip Operasional Keuangan')
            ->assertDontSee('Arsip Operasional Pengadaan');
        $this->actingAs($staff)->get(route('archives.create'))->assertOk();
        $this->actingAs($staff)
            ->get(route('qr.index'))
            ->assertOk()
            ->assertSee('Arsip Operasional Keuangan')
            ->assertDontSee('Arsip Operasional Pengadaan');
        $this->actingAs($staff)->get(route('boxes.show', 2))->assertNotFound();
        $this->actingAs($staff)->get(route('qr.box-label', 2))->assertNotFound();
        $this->actingAs($staff)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
        $this->assertTrue(RolePermissions::allows($staff, RolePermissions::ARCHIVES_MANAGE));
        $this->assertFalse(RolePermissions::allows($staff, RolePermissions::LOANS_APPROVE));
        $this->assertFalse(RolePermissions::allows($staff, RolePermissions::USERS_MANAGE));

        $this->actingAs($employee)
            ->get(route('archives.index'))
            ->assertOk()
            ->assertSee('Arsip Operasional Keuangan')
            ->assertDontSee('Arsip Operasional Pengadaan');
        $this->actingAs($employee)->get(route('archives.show', $ownArchive))->assertOk();
        $this->actingAs($employee)->get(route('archives.show', $otherArchive))->assertForbidden();
        $this->actingAs($employee)->get(route('archives.create'))->assertForbidden();
        $this->actingAs($employee)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('users.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('loan-requests.index'))->assertOk();
        $this->actingAs($employee)
            ->post(route('loan-requests.store', $ownArchive), ['purpose' => 'Kebutuhan kerja'])
            ->assertRedirect(route('loan-requests.index'));
        $this->assertDatabaseHas('loan_requests', [
            'user_id' => $employee->id,
            'arsip_id' => $ownArchive,
            'status' => 'Menunggu Persetujuan',
        ]);
        $this->actingAs($employee)
            ->post(route('loan-requests.store', $otherArchive), ['purpose' => 'Permintaan lintas divisi'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('archives.index'))
            ->assertOk()
            ->assertSee('Arsip Operasional Keuangan')
            ->assertSee('Arsip Operasional Pengadaan');
        $this->actingAs($this->admin)->get(route('users.index'))->assertOk();
        $this->assertTrue(RolePermissions::allows($this->admin, RolePermissions::LOANS_APPROVE));
        $this->assertTrue(RolePermissions::allows($this->admin, RolePermissions::USERS_MANAGE));

        foreach (['Petugas Arsip', 'Pegawai'] as $role) {
            $username = strtolower(str_replace(' ', '-', $role)).'-baru';
            $this->actingAs($this->admin)
                ->post(route('users.store'), [
                    'nama' => $role.' Baru',
                    'username' => $username,
                    'password' => 'long-enough-password',
                    'role' => $role,
                    'division' => 'Keuangan',
                ])
                ->assertRedirect(route('users.index'));
            $this->assertDatabaseHas('users', ['username' => $username, 'role' => $role]);
        }

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'nama' => 'Admin tambahan',
                'username' => 'admin-tambahan',
                'password' => 'long-enough-password',
                'role' => 'Admin',
                'division' => 'Keuangan',
            ])
            ->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['username' => 'admin-tambahan']);
    }

    public function test_operational_admin_creates_records_owned_by_its_division(): void
    {
        $admin = User::query()->create([
            'nama' => 'Petugas Arsip Keuangan',
            'username' => 'petugas-keuangan',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        $this->actingAs($admin)
            ->post(route('archives.store'), $this->archiveData([
                'classification' => 'Publik',
                'division' => 'Pengadaan',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('arsip', [
            'nama_berkas' => 'Laporan Keuangan',
            'classification' => 'Publik',
            'division' => 'Keuangan',
        ]);
    }

    public function test_operational_admin_is_scoped_to_its_archives_but_cannot_approve_loans(): void
    {
        $divisionAdmin = User::query()->create([
            'nama' => 'Admin Divisi',
            'username' => 'admin-keuangan',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $customer = User::query()->create([
            'nama' => 'Pemohon',
            'username' => 'pemohon-admin-divisi',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => null,
        ]);
        $inDivisionArchive = $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Divisi Keuangan',
            'classification' => 'Internal Divisi',
            'division' => 'keuangan',
        ]);
        $outOfDivisionArchive = $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Arsip Divisi Pengadaan',
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
        ]);
        $confidentialArchive = $this->insertArchive([
            'id' => 3,
            'nama_berkas' => 'Arsip Rahasia Keuangan',
            'classification' => 'Dokumen Rahasia',
            'division' => 'Keuangan',
        ]);

        $this->actingAs($divisionAdmin)
            ->get(route('archives.index'))
            ->assertOk()
            ->assertSee('Arsip Divisi Keuangan')
            ->assertDontSee('Arsip Divisi Pengadaan')
            ->assertDontSee('Arsip Rahasia Keuangan');
        $this->actingAs($divisionAdmin)->get(route('archives.show', $inDivisionArchive))->assertOk();
        $this->actingAs($divisionAdmin)->get(route('archives.show', $outOfDivisionArchive))->assertForbidden();
        $this->actingAs($divisionAdmin)->get(route('archives.show', $confidentialArchive))->assertForbidden();

        $inDivisionRequest = DB::table('loan_requests')->insertGetId([
            'user_id' => $customer->id,
            'arsip_id' => $inDivisionArchive,
            'purpose' => 'Keperluan divisi',
            'status' => 'Menunggu Persetujuan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $outOfDivisionRequest = DB::table('loan_requests')->insertGetId([
            'user_id' => $customer->id,
            'arsip_id' => $outOfDivisionArchive,
            'purpose' => 'Keperluan di luar divisi',
            'status' => 'Menunggu Persetujuan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($divisionAdmin)
            ->get(route('loan-approvals.index'))
            ->assertForbidden();
        $this->actingAs($divisionAdmin)
            ->patch(route('loan-approvals.decide', $outOfDivisionRequest), [
                'decision' => 'Ditolak',
                'reviewer_note' => 'Di luar cakupan divisi.',
            ])
            ->assertForbidden();
        $this->assertDatabaseHas('loan_requests', [
            'id' => $outOfDivisionRequest,
            'status' => 'Menunggu Persetujuan',
            'reviewed_by' => null,
        ]);
        $this->actingAs($divisionAdmin)
            ->patch(route('loan-approvals.decide', $inDivisionRequest), [
                'decision' => 'Ditolak',
                'reviewer_note' => 'Permintaan ditolak.',
            ])
            ->assertForbidden();
        $this->assertDatabaseHas('loan_requests', [
            'id' => $inDivisionRequest,
            'status' => 'Menunggu Persetujuan',
            'reviewed_by' => null,
        ]);
    }

    public function test_qr_tools_are_available_to_managers_and_scan_obeys_archive_access(): void
    {
        $archiveId = $this->insertArchive([
            'id' => 1,
            'classification' => 'Publik',
            'qr_code' => 'ARSIP-2026-04-000001',
        ]);

        $this->actingAs($this->admin)
            ->get(route('qr.index'))
            ->assertOk()
            ->assertSee('ARSIP-2026-04-000001')
            ->assertSee('data-barcode', false);

        $this->actingAs($this->admin)
            ->get(route('qr.archive-label', $archiveId))
            ->assertOk()
            ->assertSee('Unduh QR PNG');

        $this->actingAs($this->admin)
            ->get(route('qr.scanner'))
            ->assertOk()
            ->assertSee('id="qr-scanner-app"', false)
            ->assertSee(route('qr.lookup'), false)
            ->assertSee('Memuat pemindai kode arsip');

        $this->actingAs($this->admin)
            ->get(route('qr.lookup', ['code' => 'ARSIP-2026-04-000001']))
            ->assertRedirect(route('archives.show', $archiveId));

        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => $archiveId,
            'aktivitas' => 'Scan QR',
            'user_id' => $this->admin->id,
        ]);

        $staff = User::query()->create([
            'nama' => 'Petugas',
            'username' => 'petugas-qr',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $restrictedId = $this->insertArchive([
            'id' => 2,
            'classification' => 'Dokumen Rahasia',
            'qr_code' => 'ARSIP-2026-04-000002',
        ]);

        $this->actingAs($staff)
            ->get(route('qr.lookup', ['code' => 'ARSIP-2026-04-000002']))
            ->assertForbidden();

        $this->assertDatabaseMissing('riwayat_arsip', [
            'arsip_id' => $restrictedId,
            'aktivitas' => 'Scan QR',
            'user_id' => $staff->id,
        ]);
    }

    public function test_qr_print_all_mode_includes_filtered_records_beyond_the_page_size(): void
    {
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip QR 1',
            'qr_code' => 'ARSIP-2026-04-000001',
        ]);
        for ($id = 2; $id <= 50; $id++) {
            $this->insertArchive([
                'id' => $id,
                'nama_berkas' => 'Arsip QR '.$id,
                'nomor_berkas' => 'QR-'.$id,
                'qr_code' => sprintf('ARSIP-2026-04-%06d', $id),
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('qr.index', ['tahun' => 2026]))
            ->assertOk()
            ->assertSee('50 label cocok')
            ->assertDontSee('ARSIP-2026-04-000050');

        $this->actingAs($this->admin)
            ->get(route('qr.index', ['tahun' => 2026, 'cetak' => 1]))
            ->assertOk()
            ->assertSee('50 seluruh hasil filter')
            ->assertSee('ARSIP-2026-04-000050')
            ->assertSee('Cetak semua label');
    }

    public function test_non_manager_cannot_access_qr_label_management(): void
    {
        $customer = User::query()->create([
            'nama' => 'Pengguna',
            'username' => 'customer-qr',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => null,
        ]);

        $this->actingAs($customer)->get(route('qr.index'))->assertForbidden();
    }

    public function test_guests_must_authenticate_before_archive_mutations(): void
    {
        $archiveId = $this->insertArchive(['id' => 1]);

        $this->post(route('archives.store'), $this->archiveData())
            ->assertRedirect(route('login'));

        $this->put(route('archives.update', $archiveId), $this->archiveData())
            ->assertRedirect(route('login'));
    }

    public function test_internal_division_archive_is_not_visible_to_a_different_division(): void
    {
        $archiveId = $this->insertArchive([
            'id' => 1,
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
        ]);
        $staff = User::query()->create([
            'nama' => 'Petugas',
            'username' => 'petugas',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        $this->actingAs($staff)
            ->get(route('archives.show', $archiveId))
            ->assertForbidden();
    }

    public function test_super_admin_dashboard_displays_archive_statistics_and_recent_records(): void
    {
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Dashboard target',
            'tanggal_masuk' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('totalArchives')
            ->assertSee('Dashboard target')
            ->assertSee('data-dashboard-clock')
            ->assertSee('data-sidebar-controls')
            ->assertSee(now()->timezone(config('app.timezone'))->translatedFormat('l, d F Y'))
            ->assertSee('1', false);
    }

    public function test_pegawai_dashboard_uses_real_division_scoped_and_user_owned_data(): void
    {
        $pegawai = User::query()->create([
            'nama' => 'Pegawai Dashboard',
            'username' => 'pegawai-dashboard',
            'password' => Hash::make('pegawai-dashboard-password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $otherUser = User::query()->create([
            'nama' => 'Pegawai Lain',
            'username' => 'pegawai-lain',
            'password' => Hash::make('pegawai-lain-password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $visibleArchive = $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip divisi pegawai',
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
        ]);
        $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Arsip divisi lain',
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
        ]);
        $this->insertArchive([
            'id' => 3,
            'nama_berkas' => 'Arsip rahasia pegawai',
            'classification' => 'Dokumen Rahasia',
            'division' => 'Keuangan',
        ]);

        foreach (['Menunggu Persetujuan', 'Disetujui', 'Ditolak'] as $status) {
            DB::table('loan_requests')->insert([
                'user_id' => $pegawai->id,
                'arsip_id' => $visibleArchive,
                'purpose' => 'Keperluan '.$status,
                'requested_due_date' => today()->addWeek()->toDateString(),
                'status' => $status,
                'reviewer_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'loan_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('loan_requests')->insert([
            'user_id' => $otherUser->id,
            'arsip_id' => $visibleArchive,
            'purpose' => 'Permintaan milik pengguna lain',
            'requested_due_date' => null,
            'status' => 'Menunggu Persetujuan',
            'reviewer_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'loan_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('riwayat_arsip')->insert([
            [
                'arsip_id' => $visibleArchive,
                'user_id' => $pegawai->id,
                'aktivitas' => 'Aktivitas milik pegawai',
                'keterangan' => 'Riwayat pribadi',
                'created_at' => now(),
            ],
            [
                'arsip_id' => $visibleArchive,
                'user_id' => $otherUser->id,
                'aktivitas' => 'Aktivitas privat pengguna lain',
                'keterangan' => 'Tidak boleh tampil',
                'created_at' => now(),
            ],
        ]);
        DB::table('notifications')->insert([
            [
                'user_id' => $pegawai->id,
                'title' => 'Notifikasi untuk pegawai',
                'body' => 'Pemberitahuan pribadi',
                'created_at' => now(),
            ],
            [
                'user_id' => $otherUser->id,
                'title' => 'Notifikasi milik pengguna lain',
                'body' => 'Tidak boleh tampil',
                'created_at' => now(),
            ],
        ]);

        $this->actingAs($pegawai)
            ->get(route('pegawai.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Pegawai')
            ->assertSee('Arsip divisi pegawai')
            ->assertSee('Aktivitas milik pegawai')
            ->assertSee('Notifikasi untuk pegawai')
            ->assertSee('Disetujui')
            ->assertSee('Ditolak')
            ->assertDontSee('Arsip divisi lain')
            ->assertDontSee('Arsip rahasia pegawai')
            ->assertDontSee('Aktivitas privat pengguna lain')
            ->assertDontSee('Notifikasi milik pengguna lain')
            ->assertDontSee('Permintaan milik pengguna lain');

        $this->actingAs($pegawai)->get(route('dashboard'))->assertRedirect(route('pegawai.dashboard'));
        $this->actingAs($pegawai)->get(route('history.index'))
            ->assertOk()
            ->assertSee('Aktivitas milik pegawai')
            ->assertDontSee('Aktivitas privat pengguna lain');
    }

    public function test_pegawai_dashboard_is_not_available_to_other_roles(): void
    {
        $this->actingAs($this->admin)->get(route('pegawai.dashboard'))->assertForbidden();
    }

    public function test_dashboard_metrics_use_real_loan_and_category_data(): void
    {
        DB::table('kategori_arsip')->insert([
            'id' => 2,
            'nama_kategori' => 'Operasional',
            'status' => 'Aktif',
        ]);
        $loanDate = now()->startOfYear()->addMonths(2)->startOfMonth()->toDateString();
        $archiveId = $this->insertArchive([
            'id' => 1,
            'kategori_id' => 2,
            'status' => 'Dipinjam',
        ]);
        $this->insertArchive([
            'id' => 2,
            'kategori_id' => 1,
            'status' => 'Tersimpan',
        ]);
        DB::table('peminjaman')->insert([
            'arsip_id' => $archiveId,
            'nama_peminjam' => 'Peminjam aktif',
            'tanggal_pinjam' => $loanDate,
            'tanggal_rencana_kembali' => today()->subDay()->toDateString(),
            'status' => 'Dipinjam',
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
        preg_match('/<script id="dashboard-props" type="application\\/json">(.*?)<\\/script>/s', $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $dashboard = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $dashboard['activeLoans']);
        $this->assertSame(1, $dashboard['overdueLoans']);
        $this->assertSame(1, $dashboard['monthlyLoans'][2]);
        $this->assertSame([
            ['name' => 'Keuangan', 'total' => 1],
            ['name' => 'Operasional', 'total' => 1],
        ], $dashboard['categoryDistribution']);
    }

    public function test_warehouse_staff_dashboard_shows_only_archives_they_can_access(): void
    {
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip internal tersedia',
            'classification' => 'Internal',
            'division' => 'Keuangan',
        ]);
        $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Arsip divisi sendiri',
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
        ]);
        $this->insertArchive([
            'id' => 3,
            'nama_berkas' => 'Arsip divisi lain',
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
        ]);
        $this->insertArchive([
            'id' => 4,
            'nama_berkas' => 'Arsip rahasia',
            'classification' => 'Dokumen Rahasia',
        ]);
        DB::table('peminjaman')->insert([
            [
                'arsip_id' => 1,
                'nama_peminjam' => 'Peminjam internal',
                'tanggal_pinjam' => today()->toDateString(),
                'tanggal_rencana_kembali' => today()->subDay()->toDateString(),
                'status' => 'Dipinjam',
                'created_by' => $this->admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'arsip_id' => 4,
                'nama_peminjam' => 'Peminjam rahasia',
                'tanggal_pinjam' => today()->toDateString(),
                'tanggal_rencana_kembali' => today()->subDay()->toDateString(),
                'status' => 'Dipinjam',
                'created_by' => $this->admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $staff = User::query()->create([
            'nama' => 'Petugas Dashboard',
            'username' => 'petugas-dashboard',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        $response = $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Profil & pengaturan akun')
            ->assertSee('Arsip internal tersedia')
            ->assertSee('Arsip divisi sendiri')
            ->assertDontSee('Arsip divisi lain')
            ->assertDontSee('Arsip rahasia')
            ->assertDontSee('Lihat Laporan')
            ->assertSee('Tambah Arsip');
        preg_match('/<script id="dashboard-props" type="application\\/json">(.*?)<\\/script>/s', $response->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $dashboard = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $dashboard['activeLoans']);
        $this->assertSame(1, $dashboard['overdueLoans']);
    }

    public function test_warehouse_staff_can_open_account_and_change_own_password(): void
    {
        $staff = User::query()->create([
            'nama' => 'Petugas Akun',
            'username' => 'petugas-akun',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        $this->actingAs($staff)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSee('Keamanan')
            ->assertSee('Perbarui password')
            ->assertSee('.account-notification-copy small', false);

        $this->actingAs($staff)
            ->put(route('account.password'), [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect(route('account.show'));

        $this->assertTrue(Hash::check(
            'new-secure-password',
            DB::table('users')->where('id', $staff->id)->value('password')
        ));
    }

    public function test_report_filters_use_legacy_date_period_and_accept_all_category_and_location(): void
    {
        DB::table('kategori_arsip')->insert([
            'id' => 2,
            'nama_kategori' => 'Kategori Penelitian',
            'status' => 'Aktif',
        ]);
        DB::table('lokasi')->insert([
            'id' => 2,
            'gudang' => 'Gudang Penelitian',
            'rak' => 'Rak B',
            'lemari' => '02',
            'box' => 'Box Khusus',
            'status' => 'Aktif',
        ]);
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip sesuai tanggal',
            'tanggal_masuk' => '2026-04-25',
            'tahun' => 2025,
            'bulan' => 11,
            'kategori_id' => 2,
            'lokasi_id' => 2,
        ]);
        $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Arsip beda tanggal',
            'tanggal_masuk' => '2025-11-25',
            'tahun' => 2026,
            'bulan' => 4,
        ]);
        $this->insertArchive([
            'id' => 3,
            'nama_berkas' => 'Arsip beda kategori dan lokasi',
            'tanggal_masuk' => '2026-04-26',
            'status' => 'Dipinjam',
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.index', [
                'tahun' => 2026,
                'bulan' => 4,
                'kategori' => 0,
                'lokasi' => 0,
            ]))
            ->assertOk()
            ->assertSee('Arsip sesuai tanggal')
            ->assertSee('Arsip beda kategori dan lokasi')
            ->assertDontSee('Arsip beda tanggal');

        $this->actingAs($this->admin)
            ->get(route('reports.index', [
                'tahun' => 2026,
                'bulan' => 4,
                'status' => 'Tersimpan',
                'kategori' => 2,
                'lokasi' => 2,
            ]))
            ->assertOk()
            ->assertSee('Arsip sesuai tanggal')
            ->assertDontSee('Arsip beda kategori dan lokasi');

        $this->actingAs($this->admin)
            ->get(route('reports.print', ['tahun' => 2026, 'bulan' => 4]))
            ->assertOk()
            ->assertSee('Arsip sesuai tanggal');
    }

    public function test_super_admin_and_scoped_admin_can_access_reports(): void
    {
        $staff = User::query()->create([
            'nama' => 'Petugas',
            'username' => 'petugas-laporan',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        foreach (['reports.index', 'reports.print', 'reports.export'] as $route) {
            $this->actingAs($staff)
                ->get(route($route))
                ->assertForbidden();
        }

        $divisionAdmin = User::query()->create([
            'nama' => 'Admin Laporan',
            'username' => 'admin-laporan',
            'password' => bcrypt('password'),
            'role' => 'Admin',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        foreach (['reports.index', 'reports.print', 'reports.export'] as $route) {
            $this->actingAs($divisionAdmin)
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_report_query_scopes_division_and_excludes_archives_without_division(): void
    {
        $divisionAdmin = User::query()->create([
            'nama' => 'Pegawai Laporan',
            'username' => 'admin-laporan-scope',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Laporan Dalam Divisi',
            'classification' => 'Internal Divisi',
            'division' => ' keuangan ',
        ]);
        $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Laporan Divisi Lain',
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
        ]);
        $this->insertArchive([
            'id' => 3,
            'nama_berkas' => 'Laporan Publik Tanpa Divisi',
            'classification' => 'Publik',
            'division' => null,
        ]);
        $this->insertArchive([
            'id' => 4,
            'nama_berkas' => 'Laporan Rahasia Satu Divisi',
            'classification' => 'Dokumen Rahasia',
            'division' => 'Keuangan',
        ]);

        $method = new \ReflectionMethod(ReportController::class, 'reportQuery');
        $archives = $method->invoke(app(ReportController::class), [
            'tahun' => null,
            'bulan' => null,
            'status' => null,
            'kategori' => null,
            'lokasi' => null,
        ], $divisionAdmin)->get();

        $this->assertSame(['Laporan Dalam Divisi'], $archives->pluck('nama_berkas')->all());

        $this->actingAs($divisionAdmin)->get(route('reports.index'))->assertForbidden();
    }

    public function test_archive_export_query_scopes_division_and_excludes_archives_without_division(): void
    {
        $divisionAdmin = User::query()->create([
            'nama' => 'Pegawai Ekspor',
            'username' => 'admin-ekspor-scope',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Ekspor Dalam Divisi',
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
        ]);
        $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Ekspor Divisi Lain',
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
        ]);
        $this->insertArchive([
            'id' => 3,
            'nama_berkas' => 'Ekspor Publik Tanpa Divisi',
            'classification' => 'Publik',
            'division' => null,
        ]);

        $method = new \ReflectionMethod(ImportExportController::class, 'archiveExportQuery');
        $archives = $method->invoke(app(ImportExportController::class), $divisionAdmin)->get();

        $this->assertSame(['Ekspor Dalam Divisi'], $archives->pluck('nama_berkas')->all());
        $this->actingAs($divisionAdmin)
            ->get(route('imports.export', ['type' => 'arsip']))
            ->assertForbidden();
    }

    public function test_super_admin_report_and_exports_remain_global(): void
    {
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Laporan Divisi Lain',
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
        ]);
        $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Laporan Tanpa Divisi',
            'classification' => 'Publik',
            'division' => null,
        ]);
        $this->insertArchive([
            'id' => 3,
            'nama_berkas' => 'Laporan Rahasia',
            'classification' => 'Dokumen Rahasia',
            'division' => 'Keuangan',
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Laporan Divisi Lain')
            ->assertSee('Laporan Tanpa Divisi')
            ->assertSee('Laporan Rahasia');

        $response = $this->actingAs($this->admin)
            ->get(route('reports.export', ['type' => 'csv']));
        $response->assertDownload('laporan-arsip.csv');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Laporan Divisi Lain', $content);
        $this->assertStringContainsString('Laporan Tanpa Divisi', $content);
        $this->assertStringContainsString('Laporan Rahasia', $content);

        $archiveExport = $this->actingAs($this->admin)
            ->get(route('imports.export', ['type' => 'arsip']));
        $archiveExport->assertDownload();
        $archiveExportContent = $archiveExport->streamedContent();
        $this->assertStringContainsString('Laporan Divisi Lain', $archiveExportContent);
        $this->assertStringContainsString('Laporan Tanpa Divisi', $archiveExportContent);
        $this->assertStringContainsString('Laporan Rahasia', $archiveExportContent);
    }

    public function test_super_admin_can_create_categories_and_delete_or_deactivate_safely(): void
    {
        $this->actingAs($this->admin)
            ->post(route('categories.store'), ['nama_kategori' => 'Kategori Baru'])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('kategori_arsip', [
            'nama_kategori' => 'Kategori Baru',
            'status' => 'Aktif',
        ]);

        $this->insertArchive(['id' => 1, 'kategori_id' => 1]);
        $this->actingAs($this->admin)
            ->delete(route('categories.destroy', 1))
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('kategori_arsip', ['id' => 1, 'status' => 'Nonaktif']);
        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => null,
            'aktivitas' => 'Menonaktifkan Kategori',
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('categories.destroy', 2))
            ->assertRedirect(route('categories.index'));
        $this->assertDatabaseMissing('kategori_arsip', ['id' => 2]);
    }

    public function test_super_admin_can_manage_and_search_locations(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('locations.store'), [
                'gudang' => 'Gudang Penelitian',
                'rak' => 'Rak B',
                'lemari' => '02',
                'box' => 'Box Khusus',
                'capacity_boxes' => '25',
                'keterangan' => 'Penyimpanan penelitian',
            ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('lokasi', [
            'gudang' => 'Gudang Penelitian',
            'box' => 'Box Khusus',
            'capacity_boxes' => 25,
            'status' => 'Aktif',
        ]);

        $this->actingAs($this->admin)
            ->get(route('locations.index', ['q' => 'Box Khusus']))
            ->assertOk()
            ->assertSee('Gudang Penelitian')
            ->assertDontSee('Gudang Utama / Rak A / 01 / 01');

        $response = $this->actingAs($this->admin)
            ->put(route('locations.update', 2), [
                'gudang' => 'Gudang Penelitian',
                'rak' => 'Rak C',
                'lemari' => '02',
                'box' => 'Box Khusus',
                'capacity_boxes' => '',
                'keterangan' => '',
            ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('lokasi', [
            'id' => 2,
            'rak' => 'Rak C',
            'capacity_boxes' => null,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('locations.status', 2))
            ->assertRedirect(route('locations.index'));
        $this->assertDatabaseHas('lokasi', ['id' => 2, 'status' => 'Nonaktif']);
    }

    public function test_user_creation_form_posts_with_csrf_and_has_single_submit_feedback(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('<form class="user-form" method="post"', false)
            ->assertSee('action="'.route('users.store').'"', false)
            ->assertSee('data-submit-form', false)
            ->assertSee('data-submit-button', false)
            ->assertSee('name="_token"', false);
    }

    public function test_user_creation_validation_redirects_with_field_errors_and_never_flashes_password(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('users.index'))
            ->post(route('users.store'), [
                'nama' => 'Pegawai Uji',
                'username' => 'pegawai-invalid',
                'password' => 'short',
                'role' => 'Pegawai',
                'division' => 'Keuangan',
                'status' => 'Aktif',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors(['password']);

        $response->assertSessionHas('_old_input.username', 'pegawai-invalid');
        $this->assertArrayNotHasKey('password', session('_old_input', []));
        $this->assertDatabaseMissing('users', ['username' => 'pegawai-invalid']);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('Password minimal 12 karakter.')
            ->assertDontSee('validation.min.string');
    }

    public function test_duplicate_username_is_rejected_without_creating_another_account(): void
    {
        $this->actingAs($this->admin)
            ->from(route('users.index'))
            ->post(route('users.store'), [
                'nama' => 'Akun Duplikat',
                'username' => 'superadmin',
                'password' => 'a-secure-password',
                'role' => 'Pegawai',
                'division' => 'Keuangan',
                'status' => 'Aktif',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors(['username']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'username' => 'superadmin',
            'role' => 'Admin',
        ]);
    }

    public function test_only_super_admin_can_access_category_and_location_management(): void
    {
        $staff = User::query()->create([
            'nama' => 'Petugas',
            'username' => 'petugas-master-data',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        $this->actingAs($staff)->get(route('categories.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('categories.store'), ['nama_kategori' => 'Terlarang'])->assertForbidden();
        $this->actingAs($staff)->get(route('locations.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('locations.store'), [
            'gudang' => 'Tidak boleh',
            'rak' => 'A',
            'lemari' => '1',
            'box' => '1',
        ])->assertForbidden();
    }

    public function test_archive_list_can_filter_by_location(): void
    {
        DB::table('lokasi')->insert([
            'id' => 2,
            'gudang' => 'Gudang Kedua',
            'rak' => 'Rak B',
            'lemari' => '02',
            'box' => '02',
            'status' => 'Aktif',
        ]);
        $this->insertArchive(['id' => 1, 'nama_berkas' => 'Arsip lokasi satu']);
        $this->insertArchive(['id' => 2, 'nama_berkas' => 'Arsip lokasi dua', 'lokasi_id' => 2]);

        $this->actingAs($this->admin)
            ->get(route('archives.index', ['lokasi' => 2]))
            ->assertOk()
            ->assertSee('Arsip lokasi dua')
            ->assertDontSee('Arsip lokasi satu');
    }

    public function test_super_admin_can_search_read_only_activity_history(): void
    {
        DB::table('riwayat_arsip')->insert([
            'arsip_id' => null,
            'user_id' => $this->admin->id,
            'aktivitas' => 'Perubahan pilihan',
            'keterangan' => 'Catatan yang dicari',
            'created_at' => now(),
        ]);
        DB::table('riwayat_arsip')->insert([
            'arsip_id' => null,
            'user_id' => $this->admin->id,
            'aktivitas' => 'Aktivitas lain',
            'keterangan' => 'Tidak cocok',
            'created_at' => now()->subMinute(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('history.index', ['q' => 'Catatan yang dicari']))
            ->assertOk()
            ->assertSee('Perubahan pilihan')
            ->assertDontSee('Aktivitas lain');

        $this->assertDatabaseCount('riwayat_arsip', 2);
    }

    public function test_activity_history_is_restricted_to_super_admin(): void
    {
        $staff = User::query()->create([
            'nama' => 'Petugas',
            'username' => 'petugas-riwayat',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);

        $this->actingAs($staff)->get(route('history.index'))->assertForbidden();

        $divisionAdmin = User::query()->create([
            'nama' => 'Admin Divisi',
            'username' => 'admin-riwayat',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $this->actingAs($divisionAdmin)->get(route('history.index'))->assertForbidden();
    }

    public function test_activity_history_query_scopes_archive_events_by_division(): void
    {
        $divisionAdmin = User::query()->create([
            'nama' => 'Admin Divisi',
            'username' => 'admin-riwayat-scope',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $ownArchive = $this->insertArchive([
            'id' => 1,
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
        ]);
        $otherArchive = $this->insertArchive([
            'id' => 2,
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
        ]);
        $withoutDivision = $this->insertArchive([
            'id' => 3,
            'classification' => 'Publik',
            'division' => null,
        ]);
        foreach ([
            [$ownArchive, 'Riwayat Divisi Sendiri'],
            [$otherArchive, 'Riwayat Divisi Lain'],
            [$withoutDivision, 'Riwayat Arsip Tanpa Divisi'],
            [null, 'Riwayat Tanpa Arsip'],
        ] as [$archiveId, $activity]) {
            DB::table('riwayat_arsip')->insert([
                'arsip_id' => $archiveId,
                'user_id' => $this->admin->id,
                'aktivitas' => $activity,
                'created_at' => now(),
            ]);
        }

        $method = new \ReflectionMethod(ActivityHistoryController::class, 'historyQuery');
        $activities = $method->invoke(app(ActivityHistoryController::class), $divisionAdmin, '')->get();

        $this->assertSame(['Riwayat Divisi Sendiri'], $activities->pluck('aktivitas')->all());
    }

    public function test_super_admin_history_keeps_global_and_unlinked_events(): void
    {
        $archive = $this->insertArchive([
            'id' => 1,
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
        ]);
        DB::table('riwayat_arsip')->insert([
            ['arsip_id' => $archive, 'user_id' => $this->admin->id, 'aktivitas' => 'Riwayat Global', 'created_at' => now()],
            ['arsip_id' => null, 'user_id' => $this->admin->id, 'aktivitas' => 'Riwayat Tanpa Arsip', 'created_at' => now()->subMinute()],
        ]);

        $this->actingAs($this->admin)
            ->get(route('history.index'))
            ->assertOk()
            ->assertSee('Riwayat Global')
            ->assertSee('Riwayat Tanpa Arsip');
    }

    public function test_user_can_update_profile_and_change_password_with_current_password(): void
    {
        DB::table('remember_tokens')->insert([
            'user_id' => $this->admin->id,
            'token_hash' => hash('sha256', 'old-token'),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->put(route('account.profile'), ['nama' => 'Nama Diperbarui'])
            ->assertRedirect(route('account.show'));

        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'nama' => 'Nama Diperbarui']);

        $this->actingAs($this->admin)
            ->put(route('account.password'), [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect(route('account.show'));

        $this->assertTrue(Hash::check(
            'new-secure-password',
            DB::table('users')->where('id', $this->admin->id)->value('password')
        ));
        $this->assertDatabaseCount('remember_tokens', 0);
    }

    public function test_account_page_offers_profile_and_password_settings_without_customer_reset_request(): void
    {
        $customer = User::query()->create([
            'nama' => 'Customer Profil',
            'username' => 'customer-profil',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => null,
        ]);

        $this->actingAs($customer)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSee('Profil Anda')
            ->assertSee('Perbarui password')
            ->assertSee('Pilih foto')
            ->assertDontSee('Permintaan reset password baru');

        $this->assertFalse(Route::has('account.password-reset'));
    }

    public function test_user_can_upload_and_read_only_their_own_private_profile_photo(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $customer = User::query()->create([
            'nama' => 'Customer Foto',
            'username' => 'customer-foto',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => null,
        ]);
        $otherCustomer = User::query()->create([
            'nama' => 'Customer Foto Lain',
            'username' => 'customer-foto-lain',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => null,
        ]);

        $this->actingAs($customer)
            ->put(route('account.profile'), [
                'nama' => 'Customer Foto',
                'profile_image' => UploadedFile::fake()->create('foto.png', 10, 'image/png'),
            ])
            ->assertRedirect(route('account.show'));

        $path = DB::table('users')->where('id', $customer->id)->value('profile_image');
        $this->assertStringStartsWith('profile-images/', $path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($customer)
            ->get(route('account.photo', basename($path)))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->actingAs($otherCustomer)
            ->get(route('account.photo', basename($path)))
            ->assertNotFound();
    }

    public function test_user_cannot_change_password_without_valid_current_password(): void
    {
        $this->actingAs($this->admin)
            ->from(route('account.show'))
            ->put(route('account.password'), [
                'current_password' => 'incorrect',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check(
            'password',
            DB::table('users')->where('id', $this->admin->id)->value('password')
        ));
    }

    public function test_users_can_only_read_and_mark_their_own_notifications(): void
    {
        $staff = User::query()->create([
            'nama' => 'Petugas',
            'username' => 'petugas-notifikasi',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $myNotification = DB::table('notifications')->insertGetId([
            'user_id' => $this->admin->id,
            'title' => 'Pemberitahuan saya',
            'body' => 'Isi pesan',
            'type' => 'info',
            'url' => './',
            'is_sent' => 1,
            'created_at' => now(),
        ]);
        $otherNotification = DB::table('notifications')->insertGetId([
            'user_id' => $staff->id,
            'title' => 'Pesan privat',
            'body' => 'Tidak boleh terlihat',
            'type' => 'info',
            'url' => './',
            'is_sent' => 1,
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Pemberitahuan saya')
            ->assertDontSee('Pesan privat');

        $this->actingAs($this->admin)
            ->patch(route('notifications.read', $otherNotification))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->patch(route('notifications.read', $myNotification))
            ->assertRedirect();
        $this->assertDatabaseHas('notifications', ['id' => $myNotification, 'read_at' => now()]);

        $this->actingAs($this->admin)
            ->patch(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));
    }

    public function test_notification_feed_returns_only_the_authenticated_users_incremental_items(): void
    {
        $staff = User::query()->create([
            'nama' => 'Petugas Feed',
            'username' => 'petugas-feed',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
        ]);
        $myNotification = DB::table('notifications')->insertGetId([
            'user_id' => $this->admin->id,
            'title' => 'Notifikasi terbaru',
            'body' => 'Pesan saya',
            'type' => 'info',
            'url' => route('notifications.index'),
            'is_sent' => 1,
            'created_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'user_id' => $staff->id,
            'title' => 'Pesan privat',
            'body' => 'Bukan untuk akun ini',
            'type' => 'info',
            'url' => './',
            'is_sent' => 0,
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('notifications.feed', ['baseline' => 1]))
            ->assertOk()
            ->assertJsonPath('latest', $myNotification)
            ->assertJsonPath('items', []);

        $this->actingAs($this->admin)
            ->getJson(route('notifications.feed', ['after' => 0]))
            ->assertOk()
            ->assertJsonPath('items.0.id', $myNotification)
            ->assertJsonMissing(['title' => 'Pesan privat'])
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($this->admin)
            ->getJson(route('notifications.feed', ['after' => $myNotification]))
            ->assertOk()
            ->assertJsonPath('items', []);
    }

    public function test_staff_can_create_and_return_a_loan_atomically(): void
    {
        $archiveId = $this->insertArchive(['id' => 1, 'status' => 'Tersimpan']);

        $this->actingAs($this->admin)
            ->post(route('loans.store'), [
                'arsip_id' => $archiveId,
                'nama_peminjam' => 'Peminjam Uji',
                'unit' => 'Keuangan',
                'tanggal_pinjam' => today()->toDateString(),
                'tanggal_rencana_kembali' => today()->addDays(7)->toDateString(),
                'keperluan' => 'Pemeriksaan dokumen',
            ])
            ->assertRedirect(route('loans.index'));

        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Dipinjam']);
        $loanId = (int) DB::table('peminjaman')->where('arsip_id', $archiveId)->value('id');
        $this->assertDatabaseHas('peminjaman', [
            'id' => $loanId,
            'nama_peminjam' => 'Peminjam Uji',
            'status' => 'Dipinjam',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('loans.return', $loanId), ['condition_return' => 'Rusak Ringan'])
            ->assertRedirect(route('loans.index'));

        $this->assertDatabaseHas('arsip', [
            'id' => $archiveId,
            'status' => 'Tersimpan',
            'physical_condition' => 'Rusak Ringan',
        ]);
        $this->assertDatabaseHas('peminjaman', [
            'id' => $loanId,
            'status' => 'Dikembalikan',
            'condition_return' => 'Rusak Ringan',
            'tanggal_dikembalikan' => today()->toDateString(),
        ]);
        $this->assertDatabaseHas('riwayat_arsip', ['arsip_id' => $archiveId, 'aktivitas' => 'Meminjam berkas']);
        $this->assertDatabaseHas('riwayat_arsip', ['arsip_id' => $archiveId, 'aktivitas' => 'Mengembalikan berkas']);
    }

    public function test_pegawai_can_borrow_an_accessible_archive_without_qr_and_track_its_transaction(): void
    {
        $pegawai = User::query()->create([
            'nama' => 'Pegawai Tanpa QR',
            'username' => 'pegawai-tanpa-qr',
            'password' => Hash::make('pegawai-password-for-loan'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $otherUser = User::query()->create([
            'nama' => 'Pegawai Lain',
            'username' => 'pegawai-peminjaman-lain',
            'password' => Hash::make('other-pegawai-password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $archiveId = $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Pinjaman Divisi',
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
            'status' => 'Tersimpan',
        ]);
        $otherEmployeeArchive = $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Pinjaman milik pegawai lain',
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
            'status' => 'Dipinjam',
        ]);
        DB::table('riwayat_arsip')->insert([
            'arsip_id' => $archiveId,
            'user_id' => $otherUser->id,
            'aktivitas' => 'Aktivitas pegawai lain',
            'keterangan' => 'Catatan internal transaksi pegawai lain.',
            'created_at' => now(),
        ]);
        DB::table('peminjaman')->insert([
            'arsip_id' => $otherEmployeeArchive,
            'nama_peminjam' => $otherUser->nama,
            'unit' => $otherUser->division,
            'tanggal_pinjam' => today()->toDateString(),
            'status' => 'Dipinjam',
            'created_by' => $otherUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($pegawai)
            ->get(route('archives.show', $archiveId))
            ->assertOk()
            ->assertSee('Peminjaman tanpa QR')
            ->assertSee('Pinjam tanpa QR')
            ->assertSee('data-submit-form', false)
            ->assertSee('data-submit-button', false)
            ->assertSee(route('employee-loans.store', $archiveId))
            ->assertDontSee('Aktivitas pegawai lain')
            ->assertDontSee('Catatan internal transaksi pegawai lain.')
            ->assertDontSee($otherUser->nama);

        $this->actingAs($pegawai)
            ->post(route('employee-loans.store', $archiveId), [
                'keperluan' => 'Pemeriksaan berkas untuk pekerjaan divisi.',
                'tanggal_rencana_kembali' => today()->addDays(7)->toDateString(),
            ])
            ->assertRedirect(route('employee-loans.index'))
            ->assertSessionHas('success');

        $loan = DB::table('peminjaman')->where('arsip_id', $archiveId)->first();
        $this->assertNotNull($loan);
        $this->assertSame($pegawai->id, (int) $loan->created_by);
        $this->assertSame($pegawai->nama, $loan->nama_peminjam);
        $this->assertSame($pegawai->division, $loan->unit);
        $this->assertSame(today()->toDateString(), $loan->tanggal_pinjam);
        $this->assertSame(today()->addDays(7)->toDateString(), $loan->tanggal_rencana_kembali);
        $this->assertSame('Dipinjam', $loan->status);
        $this->assertSame('Pemeriksaan berkas untuk pekerjaan divisi.', $loan->keperluan);
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Dipinjam']);
        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => $archiveId,
            'user_id' => $pegawai->id,
            'aktivitas' => 'Meminjam berkas',
        ]);
        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => $archiveId,
            'keterangan' => 'Metode: Tanpa QR; Transaksi #'.$loan->id.'; Peminjam: '.$pegawai->nama.'; Unit: Keuangan',
        ]);
        $this->assertDatabaseCount('loan_requests', 0);
        $this->actingAs($pegawai)->get(route('archives.show', $archiveId))
            ->assertOk()
            ->assertSee('Pegawai Tanpa QR')
            ->assertSee('Meminjam berkas')
            ->assertDontSee('Aktivitas pegawai lain')
            ->assertDontSee($otherUser->nama);
        $this->actingAs($pegawai)
            ->post(route('employee-loans.store', $archiveId), [])
            ->assertStatus(409);

        $this->actingAs($pegawai)
            ->get(route('employee-loans.index'))
            ->assertOk()
            ->assertSee('Arsip Pinjaman Divisi')
            ->assertSee('Dipinjam')
            ->assertSee('transaksi tanpa QR')
            ->assertDontSee('Pinjaman milik pegawai lain');
        $this->actingAs($pegawai)
            ->get(route('pegawai.dashboard'))
            ->assertOk()
            ->assertSee('Peminjaman aktif')
            ->assertSee('Sudah dikembalikan')
            ->assertSee('Pemeriksaan berkas untuk pekerjaan divisi.')
            ->assertSee('Permintaan peminjaman terbaru')
            ->assertSee(route('loan-requests.index'));
    }

    public function test_admin_returning_pegawai_loan_updates_status_and_notifies_employee(): void
    {
        $pegawai = User::query()->create([
            'nama' => 'Pegawai Pengembali',
            'username' => 'pegawai-pengembali',
            'password' => Hash::make('pegawai-password-for-return'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $archiveId = $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip untuk dikembalikan',
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
            'status' => 'Dipinjam',
        ]);
        $loanId = DB::table('peminjaman')->insertGetId([
            'arsip_id' => $archiveId,
            'nama_peminjam' => $pegawai->nama,
            'unit' => $pegawai->division,
            'tanggal_pinjam' => today()->subDays(2)->toDateString(),
            'tanggal_rencana_kembali' => today()->toDateString(),
            'status' => 'Dipinjam',
            'created_by' => $pegawai->id,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->actingAs($this->admin)
            ->patch(route('loans.return', $loanId), ['condition_return' => 'Rusak Ringan'])
            ->assertRedirect(route('loans.index'));

        $this->assertDatabaseHas('peminjaman', [
            'id' => $loanId,
            'status' => 'Dikembalikan',
            'tanggal_dikembalikan' => today()->toDateString(),
            'condition_return' => 'Rusak Ringan',
        ]);
        $this->assertDatabaseHas('arsip', [
            'id' => $archiveId,
            'status' => 'Tersimpan',
            'physical_condition' => 'Rusak Ringan',
        ]);
        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => $archiveId,
            'user_id' => $this->admin->id,
            'aktivitas' => 'Mengembalikan berkas',
            'keterangan' => 'Transaksi peminjaman #'.$loanId,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $pegawai->id,
            'title' => 'Peminjaman dikembalikan',
        ]);

        $this->actingAs($pegawai)
            ->get(route('employee-loans.index'))
            ->assertOk()
            ->assertSee('Dikembalikan')
            ->assertSee(today()->format('d/m/Y'))
            ->assertSee('Kondisi saat dikembalikan: Rusak Ringan');
        $this->actingAs($pegawai)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Peminjaman dikembalikan')
            ->assertSee('Arsip untuk dikembalikan')
            ->assertSee('Rusak Ringan');
        $this->actingAs($pegawai)
            ->get(route('archives.show', $archiveId))
            ->assertOk()
            ->assertSee('Mengembalikan berkas');
    }

    public function test_pegawai_cannot_borrow_archives_outside_their_access_or_use_admin_loan_routes(): void
    {
        $pegawai = User::query()->create([
            'nama' => 'Pegawai Pembatasan Pinjam',
            'username' => 'pegawai-batasan-pinjam',
            'password' => Hash::make('pegawai-password-for-loan'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $otherDivisionArchive = $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Divisi Lain',
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
            'status' => 'Tersimpan',
        ]);
        $confidentialArchive = $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Arsip Rahasia',
            'classification' => 'Dokumen Rahasia',
            'division' => 'Keuangan',
            'status' => 'Tersimpan',
        ]);

        $this->actingAs($pegawai)
            ->post(route('employee-loans.store', $otherDivisionArchive))
            ->assertForbidden();
        $this->actingAs($pegawai)
            ->post(route('employee-loans.store', $confidentialArchive))
            ->assertForbidden();
        $this->actingAs($pegawai)->get(route('loans.index'))->assertForbidden();
        $this->actingAs($pegawai)->get(route('loans.create'))->assertForbidden();
        $this->actingAs($pegawai)->post(route('loans.store'), [])->assertForbidden();
        $this->actingAs($pegawai)->get(route('loan-requests.index'))->assertOk();
        $this->actingAs($pegawai)->get(route('users.index'))->assertForbidden();
        $this->actingAs($pegawai)->get(route('retention.index'))->assertForbidden();
        $this->assertDatabaseCount('peminjaman', 0);
        $this->assertDatabaseHas('arsip', ['id' => $otherDivisionArchive, 'status' => 'Tersimpan']);
        $this->assertDatabaseHas('arsip', ['id' => $confidentialArchive, 'status' => 'Tersimpan']);
    }

    public function test_pegawai_can_submit_and_track_a_public_archive_request_for_approval(): void
    {
        $pegawai = User::query()->create([
            'nama' => 'Pegawai Pemohon',
            'username' => 'pegawai-pemohon',
            'password' => Hash::make('pegawai-password-for-request'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $archiveId = $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Publik Permohonan',
            'classification' => 'Publik',
            'division' => 'Keuangan',
            'status' => 'Tersimpan',
        ]);

        $this->actingAs($pegawai)
            ->get(route('archives.show', $archiveId))
            ->assertOk()
            ->assertSee('Ajukan peminjaman · perlu persetujuan')
            ->assertSee(route('loan-requests.store', $archiveId))
            ->assertSee('Pinjam tanpa QR')
            ->assertSee(route('employee-loans.store', $archiveId));

        $this->actingAs($pegawai)
            ->post(route('loan-requests.store', $archiveId), [
                'purpose' => 'Keperluan pekerjaan divisi.',
                'requested_due_date' => today()->addDays(5)->toDateString(),
            ])
            ->assertRedirect(route('loan-requests.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('loan_requests', [
            'user_id' => $pegawai->id,
            'arsip_id' => $archiveId,
            'purpose' => 'Keperluan pekerjaan divisi.',
            'status' => 'Menunggu Persetujuan',
        ]);
        $this->actingAs($pegawai)
            ->post(route('employee-loans.store', $archiveId), [])
            ->assertStatus(409);
        $this->assertDatabaseCount('peminjaman', 0);
        $this->actingAs($pegawai)
            ->get(route('loan-requests.index'))
            ->assertOk()
            ->assertSee('Arsip Publik Permohonan')
            ->assertSee('Menunggu Persetujuan');
        $this->actingAs($pegawai)
            ->get(route('pegawai.dashboard'))
            ->assertOk()
            ->assertSee('Menunggu persetujuan')
            ->assertSee('Permintaan peminjaman terbaru')
            ->assertSee('Arsip Publik Permohonan')
            ->assertSee('Menunggu Persetujuan');
        $requestId = (int) DB::table('loan_requests')->where('user_id', $pegawai->id)->value('id');
        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => $archiveId,
            'user_id' => $pegawai->id,
            'aktivitas' => 'Mengajukan peminjaman',
            'keterangan' => 'Permintaan #'.$requestId.'; Status: Menunggu Persetujuan',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('loan-approvals.decide', $requestId), ['decision' => 'Disetujui'])
            ->assertRedirect(route('loan-approvals.index'));
        $loanId = (int) DB::table('loan_requests')->where('id', $requestId)->value('loan_id');
        $this->assertGreaterThan(0, $loanId);
        $this->actingAs($pegawai)
            ->get(route('employee-loans.index'))
            ->assertOk()
            ->assertSee('Arsip Publik Permohonan')
            ->assertSee('Dipinjam');
        $this->actingAs($pegawai)
            ->get(route('history.index'))
            ->assertOk()
            ->assertSee('Mengajukan peminjaman')
            ->assertSee('Memeriksa permintaan peminjaman')
            ->assertSee('Permintaan #'.$requestId.': Disetujui');

        $this->actingAs($this->admin)
            ->patch(route('loans.return', $loanId), ['condition_return' => 'Baik'])
            ->assertRedirect(route('loans.index'));
        $this->actingAs($pegawai)
            ->get(route('employee-loans.index'))
            ->assertOk()
            ->assertSee('Dikembalikan')
            ->assertSee('Kondisi saat dikembalikan: Baik');
        $this->actingAs($pegawai)
            ->get(route('history.index'))
            ->assertOk()
            ->assertSee('Mengembalikan berkas')
            ->assertSee('Transaksi peminjaman #'.$loanId);
        $this->actingAs($pegawai)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Peminjaman dikembalikan')
            ->assertSee('Arsip Publik Permohonan');
    }

    public function test_customer_can_request_only_available_public_archives_and_duplicate_is_blocked(): void
    {
        $customer = User::query()->create([
            'nama' => 'Customer Uji',
            'username' => 'customer-pinjaman',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Publik',
        ]);
        $archiveId = $this->insertArchive([
            'id' => 1,
            'division' => 'Publik',
            'classification' => 'Publik',
            'status' => 'Tersimpan',
        ]);

        $this->actingAs($customer)
            ->post(route('loan-requests.store', $archiveId), [
                'purpose' => 'Referensi pekerjaan',
                'requested_due_date' => today()->addDays(5)->toDateString(),
            ])
            ->assertRedirect(route('loan-requests.index'));

        $this->assertDatabaseHas('loan_requests', [
            'user_id' => $customer->id,
            'arsip_id' => $archiveId,
            'status' => 'Menunggu Persetujuan',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin->id,
            'title' => 'Permintaan peminjaman baru',
        ]);

        $this->actingAs($customer)
            ->post(route('loan-requests.store', $archiveId), ['purpose' => 'Permintaan ganda'])
            ->assertStatus(409);
    }

    public function test_approver_decision_updates_request_archive_loan_and_notification(): void
    {
        $approver = User::query()->create([
            'nama' => 'Approver',
            'username' => 'approver-loan',
            'password' => bcrypt('password'),
            'role' => 'Admin',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $customer = User::query()->create([
            'nama' => 'Customer',
            'username' => 'customer-approval',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Umum',
        ]);
        $archiveId = $this->insertArchive([
            'id' => 1,
            'classification' => 'Publik',
            'status' => 'Tersimpan',
        ]);
        $requestId = DB::table('loan_requests')->insertGetId([
            'user_id' => $customer->id,
            'arsip_id' => $archiveId,
            'purpose' => 'Keperluan kerja',
            'requested_due_date' => today()->addDays(5)->toDateString(),
            'status' => 'Menunggu Persetujuan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($approver)
            ->patch(route('loan-approvals.decide', $requestId), ['decision' => 'Disetujui'])
            ->assertRedirect(route('loan-approvals.index'));

        $loanId = (int) DB::table('loan_requests')->where('id', $requestId)->value('loan_id');
        $this->assertDatabaseHas('loan_requests', [
            'id' => $requestId,
            'status' => 'Disetujui',
            'reviewed_by' => $approver->id,
        ]);
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Dipinjam']);
        $this->assertDatabaseHas('peminjaman', [
            'id' => $loanId,
            'arsip_id' => $archiveId,
            'nama_peminjam' => 'Customer',
            'status' => 'Dipinjam',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'title' => 'Permintaan peminjaman disetujui',
        ]);
    }

    public function test_loan_request_owner_cannot_approve_or_reject_own_request(): void
    {
        $approver = User::query()->create([
            'nama' => 'Approver Pemohon',
            'username' => 'approver-pemohon',
            'password' => bcrypt('password'),
            'role' => 'Admin',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $archiveId = $this->insertArchive([
            'id' => 1,
            'classification' => 'Publik',
            'status' => 'Tersimpan',
        ]);
        $requestId = DB::table('loan_requests')->insertGetId([
            'user_id' => $approver->id,
            'arsip_id' => $archiveId,
            'purpose' => 'Permintaan historis milik approver',
            'status' => 'Menunggu Persetujuan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['Disetujui', 'Ditolak'] as $decision) {
            $this->actingAs($approver)
                ->patch(route('loan-approvals.decide', $requestId), [
                    'decision' => $decision,
                    'reviewer_note' => $decision === 'Ditolak' ? 'Catatan penolakan.' : '',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseHas('loan_requests', [
            'id' => $requestId,
            'status' => 'Menunggu Persetujuan',
            'reviewed_by' => null,
            'loan_id' => null,
        ]);
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Tersimpan']);
        $this->assertDatabaseMissing('peminjaman', ['arsip_id' => $archiveId]);
    }

    public function test_only_super_admin_can_decide_or_execute_confidential_retention(): void
    {
        Storage::fake('local');
        $archiveId = $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Retensi Rahasia',
            'classification' => 'Dokumen Rahasia',
        ]);
        $requester = User::query()->create([
            'nama' => 'Pengusul Retensi',
            'username' => 'pengusul-retensi-rahasia',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $approver = User::query()->create([
            'nama' => 'Petugas Retensi Rahasia',
            'username' => 'approver-retensi-rahasia',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $dispositionId = DB::table('disposition_requests')->insertGetId([
            'arsip_id' => $archiveId,
            'action' => 'Musnahkan',
            'reason' => 'Masa retensi berakhir.',
            'status' => 'Menunggu Persetujuan',
            'requested_by' => $requester->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($approver)
            ->patch(route('retention.dispositions.review', $dispositionId), ['status' => 'Disetujui'])
            ->assertForbidden();
        $this->assertDatabaseHas('disposition_requests', [
            'id' => $dispositionId,
            'status' => 'Menunggu Persetujuan',
            'reviewed_by' => null,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('retention.dispositions.review', $dispositionId), ['status' => 'Disetujui'])
            ->assertRedirect(route('retention.index'));
        $this->actingAs($approver)
            ->patch(route('retention.dispositions.complete', $dispositionId), [
                'minutes_number' => 'BA-RAHASIA-001',
                'minutes_note' => 'Pelaksanaan retensi.',
                'execution_date' => now()->toDateString(),
                'evidence' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti"),
            ])
            ->assertForbidden();
        $this->assertDatabaseHas('disposition_requests', [
            'id' => $dispositionId,
            'status' => 'Disetujui',
            'completed_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('retention.dispositions.complete', $dispositionId), [
                'minutes_number' => 'BA-RAHASIA-001',
                'minutes_note' => 'Pelaksanaan retensi.',
                'execution_date' => now()->toDateString(),
                'evidence' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti"),
            ])
            ->assertRedirect(route('retention.minutes', $dispositionId));
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Nonaktif']);
    }

    public function test_only_super_admin_can_verify_duplicate_pairs_containing_confidential_archives(): void
    {
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Rahasia Duplikasi',
            'classification' => 'Dokumen Rahasia',
        ]);
        $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Arsip Publik Duplikasi',
            'classification' => 'Publik',
        ]);
        $verifier = User::query()->create([
            'nama' => 'Verifikator Rahasia',
            'username' => 'verifikator-rahasia',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $reviewId = DB::table('duplicate_reviews')->insertGetId([
            'archive_id' => 1,
            'candidate_id' => 2,
            'match_reason' => 'Nomor berkas sama.',
            'status' => 'Menunggu Verifikasi',
            'created_at' => now(),
        ]);

        $this->actingAs($verifier)
            ->patch(route('duplicates.decide', $reviewId), [
                'status' => 'Duplikat',
                'reviewer_note' => 'Pasangan sama.',
            ])
            ->assertForbidden();
        $this->assertDatabaseHas('duplicate_reviews', [
            'id' => $reviewId,
            'status' => 'Menunggu Verifikasi',
            'reviewed_by' => null,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('duplicates.decide', $reviewId), [
                'status' => 'Duplikat',
                'reviewer_note' => 'Pasangan sama.',
            ])
            ->assertRedirect(route('duplicates.index'));
        $this->assertDatabaseHas('duplicate_reviews', [
            'id' => $reviewId,
            'status' => 'Duplikat',
            'reviewed_by' => $this->admin->id,
        ]);
    }

    public function test_non_super_admin_cannot_decide_a_loan_request_for_a_confidential_archive(): void
    {
        $archiveId = $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Pinjaman Rahasia',
            'classification' => 'Dokumen Rahasia',
        ]);
        $customer = User::query()->create([
            'nama' => 'Pemohon Pinjaman Rahasia',
            'username' => 'pemohon-pinjaman-rahasia',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => null,
        ]);
        $approver = User::query()->create([
            'nama' => 'Petugas Pinjaman Rahasia',
            'username' => 'approver-pinjaman-rahasia',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $requestId = DB::table('loan_requests')->insertGetId([
            'user_id' => $customer->id,
            'arsip_id' => $archiveId,
            'purpose' => 'Penggunaan internal.',
            'status' => 'Menunggu Persetujuan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($approver)
            ->patch(route('loan-approvals.decide', $requestId), ['decision' => 'Disetujui'])
            ->assertForbidden();
        $this->assertDatabaseHas('loan_requests', [
            'id' => $requestId,
            'status' => 'Menunggu Persetujuan',
            'reviewed_by' => null,
        ]);
        $this->assertDatabaseMissing('peminjaman', ['arsip_id' => $archiveId]);
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Tersimpan']);
    }

    public function test_admin_can_manage_staff_but_cannot_create_another_admin(): void
    {
        $staff = User::query()->create([
            'nama' => 'Petugas User',
            'username' => 'petugas-user',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => null,
        ]);

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'nama' => 'Petugas Baru',
                'username' => 'petugas-baru',
                'password' => 'new-staff-password',
                'role' => 'Petugas Arsip',
                'division' => 'Pelayanan',
            ])
            ->assertRedirect(route('users.index'));
        $newUserId = (int) DB::table('users')->where('username', 'petugas-baru')->value('id');
        $this->assertDatabaseHas('users', [
            'id' => $newUserId,
            'nama' => 'Petugas Baru',
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Pelayanan',
        ]);
        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Petugas Baru');

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'nama' => 'Admin tambahan',
                'username' => 'admin-tambahan',
                'password' => 'another-secure-password',
                'role' => 'Admin',
            ])
            ->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['username' => 'admin-tambahan']);

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'nama' => 'Pegawai Baru',
                'username' => 'pegawai-baru',
                'password' => 'pegawai-secure-password',
                'role' => 'Pegawai',
                'division' => 'Arsip',
                'status' => 'Aktif',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', 'Akun pengguna berhasil ditambahkan.');
        $this->assertAuthenticatedAs($this->admin);
        $pegawaiBaru = User::query()->where('username', 'pegawai-baru')->firstOrFail();
        $this->assertSame('Pegawai', $pegawaiBaru->role);
        $this->assertSame('Aktif', $pegawaiBaru->status);
        $this->assertSame('Arsip', $pegawaiBaru->division);
        $this->assertTrue(Hash::check('pegawai-secure-password', $pegawaiBaru->password));
        $this->actingAs($pegawaiBaru)->get(route('users.index'))->assertForbidden();
        $this->actingAs($pegawaiBaru)->post(route('users.store'), [
            'nama' => 'Akun Terlarang',
            'username' => 'akun-terlarang',
            'password' => 'another-secure-password',
            'role' => 'Pegawai',
            'division' => 'Arsip',
        ])->assertForbidden();
        $this->actingAs($pegawaiBaru)->get(route('pegawai.dashboard'))->assertOk();
        $this->actingAs($pegawaiBaru)->get(route('users.index'))->assertForbidden();
        $this->actingAs($pegawaiBaru)->get(route('imports.index'))->assertForbidden();
        $this->actingAs($pegawaiBaru)->get(route('categories.index'))->assertForbidden();
        $this->actingAs($pegawaiBaru)->get(route('retention.index'))->assertForbidden();
        $this->actingAs($pegawaiBaru)->get(route('loans.index'))->assertForbidden();
        $this->actingAs($pegawaiBaru)->get(route('qr.index'))->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('users.division', $staff->id), ['division' => 'Arsip'])
            ->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'division' => 'Arsip']);

        DB::table('remember_tokens')->insert([
            'user_id' => $staff->id,
            'token_hash' => hash('sha256', 'user-management-token'),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ]);
        $this->actingAs($this->admin)
            ->patch(route('users.status', $staff->id))
            ->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'status' => 'Nonaktif']);
        $this->assertDatabaseMissing('remember_tokens', ['user_id' => $staff->id]);
        $this->actingAs($staff->fresh())->get(route('account.show'))->assertRedirect(route('login'));

        $unauthorizedUser = User::query()->create([
            'nama' => 'Pengguna Bukan Admin',
            'username' => 'bukan-admin-akses',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => null,
        ]);
        $this->actingAs($unauthorizedUser)->get(route('users.index'))->assertForbidden();
        $this->actingAs($unauthorizedUser)->post(route('users.store'), [
            'nama' => 'Tak Diizinkan',
            'username' => 'bukan-admin',
            'password' => 'long-password',
            'role' => 'Pegawai',
        ])->assertForbidden();
    }

    public function test_admin_account_cannot_be_disabled_from_user_management(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('users.status', $this->admin->id))
            ->assertStatus(409);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'role' => 'Admin', 'status' => 'Aktif']);
    }

    public function test_admin_can_change_staff_roles_but_cannot_change_its_own_role(): void
    {
        $customer = User::query()->create([
            'nama' => 'Customer Role',
            'username' => 'customer-role',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => null,
        ]);
        DB::table('remember_tokens')->insert([
            'user_id' => $customer->id,
            'token_hash' => hash('sha256', 'role-change-token'),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patch(route('users.role', $customer->id), ['role' => 'Petugas Arsip'])
            ->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'role' => 'Petugas Arsip']);
        $this->assertDatabaseMissing('remember_tokens', ['user_id' => $customer->id]);

        $this->actingAs($this->admin)
            ->patch(route('users.role', $this->admin->id), ['role' => 'Pegawai'])
            ->assertStatus(409);

        $this->actingAs($this->admin)
            ->patch(route('users.role', $customer->id), ['role' => 'Admin'])
            ->assertSessionHasErrors('role');
    }

    public function test_password_reset_link_is_hashed_single_use_and_revokes_remember_tokens(): void
    {
        $customer = User::query()->create([
            'nama' => 'Customer Reset',
            'username' => 'customer-reset',
            'password' => bcrypt('old-password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => null,
        ]);
        DB::table('remember_tokens')->insert([
            'user_id' => $customer->id,
            'token_hash' => hash('sha256', 'remember-before-reset'),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('users.reset-link'), ['user_id' => $customer->id])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('oneTimeResetLink');
        $resetLink = session('oneTimeResetLink');
        $token = basename((string) parse_url($resetLink, PHP_URL_PATH));
        $this->assertSame(64, strlen($token));
        $reset = DB::table('password_reset_requests')->first();
        $this->assertSame(hash('sha256', $token), $reset->token_hash);
        $this->assertNotSame($token, $reset->token_hash);

        $this->get(route('password.reset', $token))->assertOk()->assertSee('customer-reset');
        $this->post(route('password.reset.store', $token), [
            'password' => 'customer-new-password',
            'password_confirmation' => 'customer-new-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(
            'customer-new-password',
            DB::table('users')->where('id', $customer->id)->value('password')
        ));
        $this->assertDatabaseHas('password_reset_requests', [
            'id' => $reset->id,
            'status' => 'Selesai',
            'token_hash' => null,
        ]);
        $this->assertDatabaseMissing('remember_tokens', ['user_id' => $customer->id]);
        $this->post(route('password.reset.store', $token), [
            'password' => 'another-new-password',
            'password_confirmation' => 'another-new-password',
        ])->assertNotFound();
    }

    public function test_super_admin_cannot_issue_reset_link_for_own_reset_request(): void
    {
        $requestId = DB::table('password_reset_requests')->insertGetId([
            'user_id' => $this->admin->id,
            'status' => 'Menunggu Verifikasi',
            'requested_at' => now(),
            'reviewed_by' => null,
            'token_hash' => null,
            'expires_at' => null,
            'completed_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->post(route('users.reset-link'), ['request_id' => $requestId])
            ->assertForbidden();

        $this->assertDatabaseHas('password_reset_requests', [
            'id' => $requestId,
            'status' => 'Menunggu Verifikasi',
            'reviewed_by' => null,
            'token_hash' => null,
        ]);
    }

    public function test_csv_import_requires_preview_and_only_commits_valid_category_rows_after_confirmation(): void
    {
        Storage::fake('local');
        $csv = "\xEF\xBB\xBFnama_kategori,keterangan,status\r\nKeuangan,Updated description,Nonaktif\r\nBaru,New category,Aktif\r\n,Invalid,Aktif\r\n";

        $this->actingAs($this->admin)
            ->post(route('imports.preview'), [
                'type' => 'kategori',
                'csv_file' => UploadedFile::fake()->createWithContent('categories.csv', $csv),
            ])
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('csvImportPreview.valid', 2)
            ->assertSessionHas('csvImportPreview.errors');

        $this->assertDatabaseCount('kategori_arsip', 1);
        $this->assertDatabaseHas('kategori_arsip', ['id' => 1, 'keterangan' => null, 'status' => 'Aktif']);

        $this->actingAs($this->admin)
            ->post(route('imports.commit'))
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('csvImportResult.imported', 1)
            ->assertSessionHas('csvImportResult.updated', 1)
            ->assertSessionHas('csvImportResult.skipped', 1);

        $this->assertDatabaseHas('kategori_arsip', [
            'id' => 1,
            'nama_kategori' => 'Keuangan',
            'keterangan' => 'Updated description',
            'status' => 'Aktif',
        ]);
        $this->assertDatabaseHas('kategori_arsip', [
            'nama_kategori' => 'Baru',
            'keterangan' => 'New category',
            'status' => 'Aktif',
        ]);
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    public function test_csv_archive_import_validates_relations_and_skips_duplicate_rows(): void
    {
        Storage::fake('local');
        $csv = "nomor_berkas,nama_berkas,kode_arsip,nama_kategori,tanggal_masuk,gudang,rak,lemari,box,classification,division,physical_condition,keterangan\r\n"
            ."ARS-100,Arsip Impor,KD-100,Keuangan,2024-03-15,Gudang Utama,Rak A,01,01,Internal Divisi,Keuangan,Baik,Imported record\r\n"
            ."ARS-100,Duplikat,KD-101,Keuangan,2024-03-16,Gudang Utama,Rak A,01,01,Internal, ,Baik,Duplicate row\r\n"
            ."ARS-102,Tanpa kategori,KD-102,Tidak Ada,2024-03-17,Gudang Utama,Rak A,01,01,Internal,,Baik,Invalid relation\r\n";

        $this->actingAs($this->admin)
            ->post(route('imports.preview'), [
                'type' => 'arsip',
                'csv_file' => UploadedFile::fake()->createWithContent('archives.csv', $csv),
            ])
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('csvImportPreview.valid', 1)
            ->assertSessionHas('csvImportPreview.errors');
        $this->assertDatabaseCount('arsip', 0);

        $this->actingAs($this->admin)
            ->post(route('imports.commit'))
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('csvImportResult.imported', 1)
            ->assertSessionHas('csvImportResult.skipped', 2);

        $this->assertDatabaseHas('arsip', [
            'nomor_berkas' => 'ARS-100',
            'nama_berkas' => 'Arsip Impor',
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
            'status' => 'Tersimpan',
            'qr_code' => 'ARSIP-2024-03-000001',
        ]);
        $this->assertDatabaseCount('arsip', 1);
        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => 1,
            'aktivitas' => 'Mengimpor arsip',
        ]);
        $this->assertDatabaseHas('qr_batches', ['tahun' => 2024, 'bulan' => 3, 'jumlah_qr' => 1]);
    }

    public function test_csv_location_import_updates_existing_unique_location_and_creates_new_location(): void
    {
        Storage::fake('local');
        $csv = "gudang,rak,lemari,box,capacity_boxes,keterangan,status\r\n"
            ."Gudang Utama,Rak A,01,01,12,Updated location,Nonaktif\r\n"
            ."Gudang Timur,Rak C,03,04,8,New location,Aktif\r\n"
            ."Gudang Salah,Rak Z,09,09,0,Invalid capacity,Aktif\r\n";

        $this->actingAs($this->admin)
            ->post(route('imports.preview'), [
                'type' => 'lokasi',
                'csv_file' => UploadedFile::fake()->createWithContent('locations.csv', $csv),
            ])
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('csvImportPreview.valid', 2);
        $this->assertDatabaseCount('lokasi', 1);

        $this->actingAs($this->admin)
            ->post(route('imports.commit'))
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('csvImportResult.imported', 1)
            ->assertSessionHas('csvImportResult.updated', 1)
            ->assertSessionHas('csvImportResult.skipped', 1);

        $this->assertDatabaseHas('lokasi', [
            'id' => 1,
            'capacity_boxes' => 12,
            'keterangan' => 'Updated location',
            'status' => 'Aktif',
        ]);
        $this->assertDatabaseHas('lokasi', [
            'gudang' => 'Gudang Timur',
            'capacity_boxes' => 8,
            'status' => 'Aktif',
        ]);
    }

    public function test_csv_exports_are_super_admin_only_and_keep_native_headers(): void
    {
        $response = $this->actingAs($this->admin)->get(route('imports.export', 'kategori'));
        $response->assertOk()->assertDownload();
        $this->assertStringContainsString('nama_kategori,keterangan,status', $response->streamedContent());

        $staff = User::query()->create([
            'nama' => 'Petugas',
            'username' => 'petugas-import-export',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $this->actingAs($staff)->get(route('imports.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('imports.export', 'kategori'))->assertForbidden();
    }

    public function test_retention_schedule_reminders_and_disposition_are_authorized_and_transactional(): void
    {
        Storage::fake('local');
        $archiveId = $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Lewat Retensi',
            'tanggal_masuk' => '2020-01-01',
            'classification' => 'Internal',
        ]);
        $this->actingAs($this->admin)
            ->post(route('retention.schedule'), [
                'kategori_id' => 1,
                'active_months' => 0,
                'inactive_months' => 1,
                'alert_days' => 90,
                'final_action' => 'Musnahkan',
            ])
            ->assertRedirect(route('retention.index'));

        $this->actingAs($this->admin)
            ->get(route('retention.index'))
            ->assertOk()
            ->assertSee('Arsip Lewat Retensi')
            ->assertSee('Jatuh Tempo');
        $this->assertDatabaseCount('retention_reminders', 1);
        $this->actingAs($this->admin)->get(route('retention.index'));
        $this->assertDatabaseCount('retention_reminders', 1);

        $requestResponse = $this->actingAs($this->admin)
            ->post(route('retention.dispositions.store'), [
                'arsip_id' => $archiveId,
                'reason' => 'Masa retensi resmi berakhir.',
            ]);
        $requestResponse->assertRedirect(route('retention.index'));
        $dispositionId = (int) DB::table('disposition_requests')->value('id');

        $this->actingAs($this->admin)
            ->patch(route('retention.dispositions.review', $dispositionId), [
                'status' => 'Disetujui',
            ])
            ->assertForbidden();

        $approver = User::query()->create([
            'nama' => 'Approver Retensi',
            'username' => 'approver-retensi',
            'password' => bcrypt('password'),
            'role' => 'Admin',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $this->actingAs($approver)
            ->patch(route('retention.dispositions.review', $dispositionId), [
                'status' => 'Ditolak',
            ])
            ->assertSessionHasErrors('reviewer_note');
        $this->actingAs($approver)
            ->patch(route('retention.dispositions.review', $dispositionId), [
                'status' => 'Disetujui',
                'reviewer_note' => '',
            ])
            ->assertRedirect(route('retention.index'));
        $this->assertDatabaseHas('disposition_requests', [
            'id' => $dispositionId,
            'status' => 'Disetujui',
            'reviewed_by' => $approver->id,
        ]);
        $this->actingAs($approver)->get(route('retention.index'))
            ->assertOk()
            ->assertSee('Tanggal pelaksanaan')
            ->assertSee('Bukti pelaksanaan / serah-terima');

        $this->actingAs($this->admin)
            ->patch(route('retention.dispositions.complete', $dispositionId), [
                'minutes_number' => 'BA-SELF-001',
                'minutes_note' => 'Pengusul mencoba melaksanakan sendiri.',
                'execution_date' => now()->toDateString(),
                'evidence' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti"),
            ])
            ->assertForbidden();
        $this->assertDatabaseHas('disposition_requests', [
            'id' => $dispositionId,
            'status' => 'Disetujui',
            'completed_at' => null,
        ]);
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Tersimpan']);

        $this->actingAs($approver)
            ->patch(route('retention.dispositions.complete', $dispositionId), [
                'minutes_number' => 'BA-001',
                'minutes_note' => 'Pemusnahan dicatat.',
                'execution_date' => now()->toDateString(),
                'evidence' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti"),
            ])
            ->assertRedirect(route('retention.minutes', $dispositionId));
        $this->assertDatabaseHas('disposition_requests', [
            'id' => $dispositionId,
            'status' => 'Dilaksanakan',
            'minutes_number' => 'BA-001',
        ]);
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Nonaktif']);
        $this->actingAs($approver)
            ->get(route('retention.minutes', $dispositionId))
            ->assertOk()
            ->assertSee('BA-001')
            ->assertSee('Pemusnahan dicatat.');
        $evidencePath = DB::table('disposition_requests')->where('id', $dispositionId)->value('evidence_path');
        $this->assertNotNull($evidencePath);
        Storage::disk('local')->assertExists($evidencePath);
        $this->actingAs($approver)->get(route('retention.evidence', $dispositionId))->assertOk();
        $this->assertDatabaseHas('disposition_requests', [
            'id' => $dispositionId,
            'executed_by' => $approver->id,
            'execution_date' => now()->toDateString(),
        ]);
        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => $archiveId,
            'aktivitas' => 'Melaksanakan penyusutan arsip',
        ]);
    }

    public function test_scheduled_retention_command_notifies_once_and_is_idempotent(): void
    {
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Jatuh Tempo',
            'tanggal_masuk' => '2020-01-01',
        ]);
        DB::table('jadwal_retensi')->insert([
            'kategori_id' => 1,
            'active_months' => 0,
            'inactive_months' => 1,
            'final_action' => 'Musnahkan',
            'alert_days' => 90,
            'updated_by' => $this->admin->id,
        ]);

        $this->artisan('retention:dispatch-reminders')
            ->expectsOutputToContain('Pengingat baru: 1.')
            ->assertExitCode(0);
        $this->artisan('retention:dispatch-reminders')
            ->expectsOutputToContain('Pengingat baru: 0.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('retention_reminders', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin->id,
            'title' => 'Retensi Berakhir',
            'body' => 'Arsip Jatuh Tempo · jatuh tempo 2020-02-01',
        ]);
        $this->assertSame(1, DB::table('notifications')->where('title', 'Retensi Berakhir')->count());
    }

    public function test_duplicate_candidates_are_detected_with_role_and_note_controls(): void
    {
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Satu',
            'nomor_berkas' => 'A/001-2026',
            'classification' => 'Publik',
        ]);
        $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Arsip Dua',
            'nomor_berkas' => 'a001 2026',
            'classification' => 'Publik',
        ]);

        $this->actingAs($this->admin)
            ->get(route('duplicates.index'))
            ->assertOk()
            ->assertSee('Jalankan discovery')
            ->assertDontSee('Arsip Satu')
            ->assertDontSee('Arsip Dua');
        $this->assertDatabaseCount('duplicate_reviews', 0);

        $this->actingAs($this->admin)
            ->post(route('duplicates.discover'))
            ->assertRedirect(route('duplicates.index'))
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->get(route('duplicates.index'))
            ->assertOk()
            ->assertSee('Arsip Satu')
            ->assertSee('Arsip Dua')
            ->assertSee('Nomor berkas sama');
        $review = DB::table('duplicate_reviews')->first();
        $this->assertNotNull($review);

        $this->actingAs($this->admin)
            ->patch(route('duplicates.decide', $review->id), ['status' => 'Duplikat'])
            ->assertSessionHasErrors('reviewer_note');
        $this->actingAs($this->admin)
            ->patch(route('duplicates.decide', $review->id), [
                'status' => 'Duplikat',
                'reviewer_note' => 'Nomor dokumen mengidentifikasi dokumen yang sama.',
            ])
            ->assertRedirect(route('duplicates.index'));
        $this->assertDatabaseHas('duplicate_reviews', [
            'id' => $review->id,
            'status' => 'Duplikat',
            'reviewed_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('riwayat_arsip', [
            'arsip_id' => $review->archive_id,
            'aktivitas' => 'Memverifikasi potensi duplikasi',
        ]);

        $customer = User::query()->create([
            'nama' => 'Customer Duplicate',
            'username' => 'customer-duplicate',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => null,
        ]);
        $this->actingAs($customer)->get(route('duplicates.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('duplicates.discover'))->assertForbidden();

        $verifier = User::query()->create([
            'nama' => 'Verifikator',
            'username' => 'verifikator-duplikasi',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $this->assertTrue(RolePermissions::allows($verifier, RolePermissions::DUPLICATES_REVIEW));
        $this->assertFalse(RolePermissions::allows($verifier, RolePermissions::USERS_MANAGE));
        $this->actingAs($verifier)->get(route('duplicates.index'))->assertOk();
        $this->actingAs($verifier)->post(route('duplicates.discover'))->assertRedirect(route('duplicates.index'));
        $this->actingAs($verifier)->get(route('qr.scanner'))->assertOk();
        $this->actingAs($verifier)->get(route('reports.index'))->assertForbidden();
    }

    public function test_duplicate_discovery_is_deterministic_and_continues_past_existing_pairs(): void
    {
        for ($id = 1; $id <= 34; $id++) {
            $this->insertArchive([
                'id' => $id,
                'nama_berkas' => 'Arsip Kandidat Batch',
                'nomor_berkas' => sprintf('BATCH-%03d', $id),
                'classification' => 'Publik',
            ]);
        }
        DB::table('duplicate_reviews')->insert([
            'archive_id' => 1,
            'candidate_id' => 2,
            'match_reason' => 'Alasan lama',
            'status' => 'Duplikat',
            'reviewer_note' => 'Keputusan dipertahankan.',
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now(),
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('duplicates.discover'))
            ->assertRedirect(route('duplicates.index'))
            ->assertSessionHas('success', 'Discovery selesai: 500 kandidat baru ditambahkan. Maksimal 500 pasangan diproses per batch; jalankan kembali untuk melanjutkan bila masih ada pasangan.');
        $this->assertDatabaseCount('duplicate_reviews', 501);
        $this->assertDatabaseHas('duplicate_reviews', [
            'archive_id' => 1,
            'candidate_id' => 2,
            'match_reason' => 'Alasan lama',
            'status' => 'Duplikat',
            'reviewer_note' => 'Keputusan dipertahankan.',
        ]);
        $this->assertDatabaseHas('duplicate_reviews', ['archive_id' => 1, 'candidate_id' => 3]);
        $this->assertDatabaseMissing('duplicate_reviews', ['archive_id' => 23, 'candidate_id' => 30]);

        $this->actingAs($this->admin)->get(route('duplicates.index'))->assertOk();
        $this->assertDatabaseCount('duplicate_reviews', 501);
        $this->assertDatabaseHas('duplicate_reviews', [
            'archive_id' => 1,
            'candidate_id' => 2,
            'match_reason' => 'Alasan lama',
            'status' => 'Duplikat',
            'reviewer_note' => 'Keputusan dipertahankan.',
        ]);

        $this->actingAs($this->admin)->post(route('duplicates.discover'))->assertRedirect(route('duplicates.index'));
        $this->assertDatabaseCount('duplicate_reviews', 561);
        $this->assertDatabaseHas('duplicate_reviews', ['archive_id' => 23, 'candidate_id' => 30]);

        $this->actingAs($this->admin)
            ->post(route('duplicates.discover'))
            ->assertRedirect(route('duplicates.index'))
            ->assertSessionHas('success', 'Tidak ada kandidat duplikasi baru yang ditemukan.');
        $this->assertDatabaseCount('duplicate_reviews', 561);
    }

    public function test_duplicate_discovery_scopes_both_archive_sides_to_head_division(): void
    {
        $divisionAdmin = User::query()->create([
            'nama' => 'Admin Duplikasi',
            'username' => 'admin-duplikasi-scope',
            'password' => bcrypt('password'),
            'role' => 'Petugas Arsip',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Cocok',
            'nomor_berkas' => 'PAIR-A',
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
        ]);
        $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Arsip Cocok',
            'nomor_berkas' => 'PAIR-B',
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
        ]);
        $this->insertArchive([
            'id' => 3,
            'nama_berkas' => 'Arsip Cocok',
            'nomor_berkas' => 'PAIR-C',
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
        ]);
        $this->insertArchive([
            'id' => 4,
            'nama_berkas' => 'Arsip Cocok',
            'nomor_berkas' => 'PAIR-D',
            'classification' => 'Internal Divisi',
            'division' => 'Pengadaan',
        ]);

        app(DuplicateCandidateDiscovery::class)->discover($divisionAdmin);
        $this->assertDatabaseCount('duplicate_reviews', 1);
        $this->assertDatabaseHas('duplicate_reviews', ['archive_id' => 1, 'candidate_id' => 2]);
        $this->assertDatabaseMissing('duplicate_reviews', ['archive_id' => 3, 'candidate_id' => 4]);
    }

    public function test_only_assigned_duplicate_review_target_roles_can_discover_candidates(): void
    {
        $admin = User::query()->create([
            'nama' => 'Admin Operasional Duplikasi',
            'username' => 'admin-operasional-duplikasi',
            'password' => bcrypt('password'),
            'role' => 'Admin',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $this->assertTrue(RolePermissions::allows($admin, RolePermissions::DUPLICATES_REVIEW));
        $this->actingAs($admin)->post(route('duplicates.discover'))->assertRedirect(route('duplicates.index'));

        $employee = User::query()->create([
            'nama' => 'Pegawai Tanpa Verifikasi',
            'username' => 'pegawai-tanpa-verifikasi',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => 'Keuangan',
        ]);
        $this->assertFalse(RolePermissions::allows($employee, RolePermissions::DUPLICATES_REVIEW));
        $this->actingAs($employee)->post(route('duplicates.discover'))->assertForbidden();
    }

    public function test_target_roles_without_a_division_have_no_archive_scope(): void
    {
        $user = User::query()->create([
            'nama' => 'Pegawai tanpa divisi',
            'username' => 'pegawai-tanpa-divisi',
            'password' => bcrypt('password'),
            'role' => 'Pegawai',
            'status' => 'Aktif',
            'division' => null,
        ]);

        $this->assertSame([], ArchiveAccess::scope(DB::table('arsip as a'), $user)->pluck('a.id')->all());
    }

    public function test_duplicate_discovery_rolls_back_batch_and_logs_database_errors(): void
    {
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Error',
            'nomor_berkas' => 'ERR-A',
            'classification' => 'Publik',
        ]);
        $this->insertArchive([
            'id' => 2,
            'nama_berkas' => 'Arsip Error',
            'nomor_berkas' => 'ERR-B',
            'classification' => 'Publik',
        ]);
        $this->insertArchive([
            'id' => 3,
            'nama_berkas' => 'Arsip Error',
            'nomor_berkas' => 'ERR-C',
            'classification' => 'Publik',
        ]);
        DB::statement("CREATE TRIGGER fail_duplicate_discovery BEFORE INSERT ON duplicate_reviews WHEN NEW.candidate_id = 3 BEGIN SELECT RAISE(ABORT, 'forced discovery error'); END");
        Log::spy();

        try {
            app(DuplicateCandidateDiscovery::class)->discover($this->admin);
            $this->fail('Database error must propagate instead of returning a success-shaped result.');
        } catch (QueryException) {
            $this->assertDatabaseCount('duplicate_reviews', 0);
        }

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Duplicate candidate discovery batch failed.', \Mockery::on(
                fn (array $context): bool => $context['user_id'] === $this->admin->id
                    && $context['exception'] === QueryException::class
            ));
    }

    public function test_report_csv_export_contains_filtered_archive_data(): void
    {
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip CSV',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.export', ['type' => 'csv']));

        $response->assertDownload('laporan-arsip.csv');
        $this->assertStringContainsString('"Nomor Berkas"', $response->streamedContent());
        $this->assertStringContainsString('Arsip CSV', $response->streamedContent());
    }

    public function test_report_xlsx_export_is_a_valid_workbook_download(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('Ekstensi ZIP diperlukan untuk menguji ekspor XLSX.');
        }

        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip Excel',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.export', ['type' => 'xlsx']));
        $response->assertDownload('laporan-arsip.xlsx');

        $file = $response->baseResponse->getFile()->getPathname();
        $workbook = new \ZipArchive;
        $this->assertSame(true, $workbook->open($file));
        $this->assertNotFalse($workbook->getFromName('xl/worksheets/sheet1.xml'));
        $this->assertStringContainsString('Arsip Excel', $workbook->getFromName('xl/worksheets/sheet1.xml'));
        $workbook->close();
        unlink($file);
    }

    public function test_report_pdf_export_downloads_a_valid_pdf(): void
    {
        $this->insertArchive([
            'id' => 1,
            'nama_berkas' => 'Arsip PDF',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.export', ['type' => 'pdf']));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=laporan-arsip.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('/Subtype /Image', $response->getContent());
    }

    public function test_distribution_runs_through_approval_dispatch_and_receipt_with_audit_history(): void
    {
        Storage::fake('local');
        $pegawai = $this->createUser('Pemohon Distribusi', 'pegawai-distribusi', 'Pegawai', 'Keuangan');
        $approver = $this->createUser('Admin Keuangan', 'admin-keuangan', 'Admin', 'Keuangan');
        $outsideApprover = $this->createUser('Petugas Legal', 'petugas-legal-distribusi', 'Petugas Arsip', 'Legal');
        $recipient = $this->createUser('Penerima Digital', 'penerima-digital', 'Pegawai', 'Digital');
        $wrongRecipient = $this->createUser('Penerima Legal', 'penerima-legal', 'Pegawai', 'Legal');
        $archiveId = $this->insertArchive([
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
            'status' => 'Tersimpan',
        ]);

        $this->actingAs($pegawai)->post(route('distributions.store'), [
            'archive_id' => $archiveId,
            'recipient_id' => $recipient->id,
            'notes' => 'Pemindahan berkas untuk unit pengelola.',
        ])->assertRedirect(route('distributions.index'));

        $distributionId = (int) DB::table('archive_distributions')->value('id');
        $this->assertDatabaseHas('archive_distributions', [
            'id' => $distributionId,
            'status' => 'Menunggu Persetujuan',
            'origin_division' => 'Keuangan',
            'destination_division' => 'Digital',
            'requested_by' => $pegawai->id,
            'recipient_id' => $recipient->id,
        ]);
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Diproses']);
        $this->actingAs($pegawai)->post(route('distributions.store'), [
            'archive_id' => $archiveId,
            'recipient_id' => $recipient->id,
        ])->assertStatus(409);
        $this->assertSame(1, DB::table('archive_distributions')->count());
        $this->actingAs($pegawai)->get(route('distributions.index'))
            ->assertOk()
            ->assertSee('Ajukan distribusi')
            ->assertSee('Menunggu Persetujuan');
        $this->actingAs($pegawai)->get(route('distributions.create'))
            ->assertOk()
            ->assertSee('Petugas penerima di unit tujuan');
        $this->actingAs($approver)->get(route('distributions.index'))
            ->assertOk()
            ->assertSee('Setujui')
            ->assertSee('Alasan penolakan');
        $this->actingAs($outsideApprover)->patch(route('distributions.review', $distributionId), [
            'decision' => 'Disetujui',
        ])->assertForbidden();

        $this->actingAs($approver)->patch(route('distributions.review', $distributionId), [
            'decision' => 'Disetujui',
        ])->assertRedirect(route('distributions.index'));
        $this->assertDatabaseHas('archive_distributions', [
            'id' => $distributionId,
            'status' => 'Disetujui',
            'approved_by' => $approver->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'title' => 'Distribusi disetujui',
        ]);

        $this->actingAs($approver)->patch(route('distributions.dispatch', $distributionId))
            ->assertRedirect(route('distributions.index'));
        $this->assertDatabaseHas('archive_distributions', [
            'id' => $distributionId,
            'status' => 'Dalam Pengiriman',
            'dispatched_by' => $approver->id,
        ]);
        $this->actingAs($recipient)->get(route('distributions.index'))
            ->assertOk()
            ->assertSee('Konfirmasi penerimaan');
        $this->actingAs($wrongRecipient)->patch(route('distributions.receive', $distributionId), [
            'receipt_note' => 'Mencoba konfirmasi dari unit berbeda.',
        ])->assertForbidden();

        $this->actingAs($recipient)->patch(route('distributions.receive', $distributionId), [
            'receipt_note' => 'Diterima lengkap dan kondisi baik.',
            'receipt_attachment' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti"),
        ])->assertRedirect(route('distributions.index'));

        $this->assertDatabaseHas('archive_distributions', [
            'id' => $distributionId,
            'status' => 'Diterima',
            'received_by' => $recipient->id,
            'receipt_note' => 'Diterima lengkap dan kondisi baik.',
        ]);
        $this->assertDatabaseHas('arsip', [
            'id' => $archiveId,
            'status' => 'Tersimpan',
            'division' => 'Digital',
        ]);
        $this->assertSame(4, DB::table('riwayat_arsip')->where('arsip_id', $archiveId)->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $pegawai->id,
            'title' => 'Arsip telah diterima',
        ]);
        $evidencePath = DB::table('archive_distributions')->where('id', $distributionId)->value('receipt_attachment');
        $this->assertNotNull($evidencePath);
        Storage::disk('local')->assertExists($evidencePath);
        $this->actingAs($recipient)->get(route('distributions.evidence', $distributionId))->assertOk();
    }

    public function test_rejected_distribution_requires_reason_and_restores_archive_availability(): void
    {
        $pegawai = $this->createUser('Pemohon', 'pegawai-tolak', 'Pegawai', 'Keuangan');
        $approver = $this->createUser('Admin', 'admin-tolak', 'Admin', 'Keuangan');
        $recipient = $this->createUser('Penerima', 'penerima-tolak', 'Pegawai', 'Digital');
        $archiveId = $this->insertArchive(['classification' => 'Internal Divisi', 'division' => 'Keuangan']);

        $this->actingAs($pegawai)->post(route('distributions.store'), [
            'archive_id' => $archiveId,
            'recipient_id' => $recipient->id,
        ])->assertRedirect(route('distributions.index'));
        $distributionId = (int) DB::table('archive_distributions')->value('id');

        $this->actingAs($approver)->from(route('distributions.index'))
            ->patch(route('distributions.review', $distributionId), ['decision' => 'Ditolak'])
            ->assertRedirect(route('distributions.index'))
            ->assertSessionHasErrors('rejection_note');
        $this->assertDatabaseHas('archive_distributions', ['id' => $distributionId, 'status' => 'Menunggu Persetujuan']);

        $this->actingAs($approver)->patch(route('distributions.review', $distributionId), [
            'decision' => 'Ditolak',
            'rejection_note' => 'Tidak ada kebutuhan operasional.',
        ])->assertRedirect(route('distributions.index'));

        $this->assertDatabaseHas('archive_distributions', [
            'id' => $distributionId,
            'status' => 'Ditolak',
            'rejection_note' => 'Tidak ada kebutuhan operasional.',
        ]);
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Tersimpan']);
    }

    public function test_distribution_rejects_unauthorized_roles_and_inaccessible_archives(): void
    {
        $pegawai = $this->createUser('Pegawai', 'pegawai-tidak-boleh', 'Pegawai', 'Keuangan');
        $recipient = $this->createUser('Penerima', 'penerima-tidak-boleh', 'Pegawai', 'Digital');
        $archiveId = $this->insertArchive(['classification' => 'Internal Divisi', 'division' => 'Keuangan']);

        $this->actingAs($pegawai)->post(route('distributions.store'), [
            'archive_id' => $archiveId,
            'recipient_id' => $recipient->id,
        ])->assertRedirect(route('distributions.index'));
        $distributionId = (int) DB::table('archive_distributions')->value('id');

        $this->actingAs($pegawai)->patch(route('distributions.review', $distributionId), [
            'decision' => 'Disetujui',
        ])->assertForbidden();
        $this->actingAs($pegawai)->patch(route('distributions.dispatch', $distributionId))->assertForbidden();

        $foreignArchiveId = $this->insertArchive([
            'id' => 2,
            'classification' => 'Internal Divisi',
            'division' => 'Legal',
            'qr_code' => 'ARSIP-2026-04-000002',
        ]);
        $this->actingAs($pegawai)->post(route('distributions.store'), [
            'archive_id' => $foreignArchiveId,
            'recipient_id' => $recipient->id,
        ])->assertForbidden();
        $this->assertSame(1, DB::table('archive_distributions')->count());
    }

    public function test_confidential_distribution_cannot_be_assigned_to_an_unauthorized_recipient(): void
    {
        $recipient = $this->createUser('Penerima Pegawai', 'pegawai-penerima-rahasia', 'Pegawai', 'Digital');
        $archiveId = $this->insertArchive([
            'classification' => 'Dokumen Rahasia',
            'division' => 'Keuangan',
        ]);

        $this->actingAs($this->admin)->post(route('distributions.store'), [
            'archive_id' => $archiveId,
            'recipient_id' => $recipient->id,
        ])->assertForbidden();

        $this->assertSame(0, DB::table('archive_distributions')->count());
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Tersimpan']);
    }

    public function test_distribution_receipt_upload_is_removed_when_database_transaction_fails(): void
    {
        Storage::fake('local');
        $pegawai = $this->createUser('Pemohon', 'pegawai-rollback', 'Pegawai', 'Keuangan');
        $approver = $this->createUser('Admin', 'admin-rollback', 'Admin', 'Keuangan');
        $recipient = $this->createUser('Penerima', 'penerima-rollback', 'Pegawai', 'Digital');
        $archiveId = $this->insertArchive(['classification' => 'Internal Divisi', 'division' => 'Keuangan']);

        $this->actingAs($pegawai)->post(route('distributions.store'), [
            'archive_id' => $archiveId,
            'recipient_id' => $recipient->id,
        ])->assertRedirect(route('distributions.index'));
        $distributionId = (int) DB::table('archive_distributions')->value('id');
        $this->actingAs($approver)->patch(route('distributions.review', $distributionId), ['decision' => 'Disetujui']);
        $this->actingAs($approver)->patch(route('distributions.dispatch', $distributionId));

        DB::statement("CREATE TRIGGER fail_distribution_receipt BEFORE UPDATE ON archive_distributions BEGIN SELECT RAISE(ABORT, 'forced failure'); END");
        $this->actingAs($recipient)->patch(route('distributions.receive', $distributionId), [
            'receipt_note' => 'Diterima.',
            'receipt_attachment' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti"),
        ])->assertInternalServerError();
        $this->assertSame([], Storage::disk('local')->allFiles('distribution-evidence'));

        $this->assertDatabaseHas('archive_distributions', [
            'id' => $distributionId,
            'status' => 'Dalam Pengiriman',
            'receipt_attachment' => null,
        ]);
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Diproses']);
    }

    public function test_pegawai_archive_detail_only_shows_their_own_loan_history(): void
    {
        $pegawai = $this->createUser('Pegawai Pembaca', 'pegawai-detail', 'Pegawai', 'Keuangan');
        $otherUser = $this->createUser('Peminjam Lain', 'peminjam-detail-lain', 'Pegawai', 'Keuangan');
        $archiveId = $this->insertArchive([
            'classification' => 'Internal Divisi',
            'division' => 'Keuangan',
            'status' => 'Dipinjam',
        ]);
        DB::table('peminjaman')->insert([
            'arsip_id' => $archiveId,
            'nama_peminjam' => $otherUser->nama,
            'unit' => $otherUser->division,
            'tanggal_pinjam' => today()->toDateString(),
            'keperluan' => 'Rincian transaksi rahasia milik pegawai lain.',
            'status' => 'Dipinjam',
            'created_by' => $otherUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($pegawai)->get(route('archives.show', $archiveId))
            ->assertOk()
            ->assertDontSee('Peminjaman Terakhir')
            ->assertDontSee($otherUser->nama)
            ->assertDontSee('Rincian transaksi rahasia milik pegawai lain.');
    }

    private function createUser(string $name, string $username, string $role, string $division): User
    {
        return User::query()->create([
            'nama' => $name,
            'username' => $username,
            'password' => Hash::make('testing-password'),
            'role' => $role,
            'status' => 'Aktif',
            'division' => $division,
        ]);
    }

    private function archiveData(array $overrides = []): array
    {
        return array_merge([
            'nomor_berkas' => '001/KEU/2026',
            'nama_berkas' => 'Laporan Keuangan',
            'kode_arsip' => 'KEU-001',
            'kategori_id' => 1,
            'tanggal_masuk' => '2026-04-22',
            'lokasi_id' => 1,
            'status' => 'Tersimpan',
            'physical_condition' => 'Baik',
            'classification' => 'Internal',
            'division' => '',
            'keterangan' => '',
        ], $overrides);
    }

    private function insertArchive(array $overrides = []): int
    {
        $id = $overrides['id'] ?? null;
        unset($overrides['id']);
        $defaultQrNumber = $id ?? 1;

        $values = array_merge([
            'nomor_berkas' => '001/KEU/2026',
            'nama_berkas' => 'Laporan Keuangan',
            'kode_arsip' => 'KEU-001',
            'kategori_id' => 1,
            'tahun' => 2026,
            'bulan' => 4,
            'tanggal_masuk' => '2026-04-22',
            'lokasi_id' => 1,
            'classification' => 'Internal',
            'division' => null,
            'physical_condition' => 'Baik',
            'status' => 'Tersimpan',
            'qr_code' => sprintf('ARSIP-2026-04-%06d', $defaultQrNumber),
            'keterangan' => null,
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('arsip')->insertGetId($id === null ? $values : ['id' => $id] + $values);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('profile_image')->nullable();
            $table->string('role');
            $table->string('division')->nullable();
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('kategori_arsip', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama_kategori')->unique();
            $table->text('keterangan')->nullable();
            $table->string('status');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('lokasi', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('gudang');
            $table->string('rak');
            $table->string('lemari');
            $table->string('box');
            $table->unsignedInteger('capacity_boxes')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('arsip', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nomor_berkas');
            $table->string('nama_berkas');
            $table->string('kode_arsip');
            $table->unsignedInteger('kategori_id');
            $table->unsignedInteger('tahun');
            $table->unsignedInteger('bulan');
            $table->date('tanggal_masuk');
            $table->unsignedInteger('lokasi_id');
            $table->string('classification');
            $table->string('division')->nullable();
            $table->string('physical_condition');
            $table->string('status');
            $table->string('qr_code')->unique();
            $table->text('keterangan')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
        });
        Schema::create('qr_batches', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('tahun');
            $table->unsignedInteger('bulan');
            $table->unsignedInteger('jumlah_qr');
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique(['tahun', 'bulan']);
        });
        Schema::create('riwayat_arsip', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('arsip_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->string('aktivitas');
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('arsip_files', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('arsip_id');
            $table->string('nama_file');
            $table->string('file_path');
            $table->string('tipe_file');
            $table->unsignedInteger('uploaded_by');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('peminjaman', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('arsip_id');
            $table->string('nama_peminjam');
            $table->string('unit')->nullable();
            $table->date('tanggal_pinjam');
            $table->date('tanggal_rencana_kembali')->nullable();
            $table->date('tanggal_dikembalikan')->nullable();
            $table->string('condition_return')->nullable();
            $table->text('keperluan')->nullable();
            $table->string('status');
            $table->unsignedInteger('created_by');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('loan_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('arsip_id');
            $table->text('purpose');
            $table->date('requested_due_date')->nullable();
            $table->string('status');
            $table->text('reviewer_note')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->unsignedInteger('loan_id')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
        Schema::create('remember_tokens', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->char('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('created_at');
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('info');
            $table->string('url')->default('./');
            $table->text('data')->nullable();
            $table->boolean('is_sent')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->dateTime('created_at');
        });
        Schema::create('password_reset_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('status');
            $table->dateTime('requested_at');
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->char('token_hash', 64)->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('completed_at')->nullable();
        });
        Schema::create('customer_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('requested_code', 180);
            $table->text('purpose');
            $table->string('status');
            $table->text('reviewer_note')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
        Schema::create('customer_submissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('arsip_id')->nullable();
            $table->string('nomor_dokumen', 180);
            $table->string('nama_dokumen', 255);
            $table->unsignedInteger('kategori_id')->nullable();
            $table->date('tanggal_dokumen');
            $table->text('keterangan')->nullable();
            $table->string('nama_file', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size');
            $table->string('status');
            $table->text('reviewer_note')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
        Schema::create('jadwal_retensi', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('kategori_id')->unique();
            $table->unsignedSmallInteger('active_months');
            $table->unsignedSmallInteger('inactive_months');
            $table->string('final_action');
            $table->unsignedSmallInteger('alert_days')->default(90);
            $table->unsignedInteger('updated_by');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('retention_reminders', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('arsip_id');
            $table->string('reminder_type');
            $table->date('due_date');
            $table->dateTime('created_at')->nullable();
            $table->unique(['arsip_id', 'reminder_type', 'due_date']);
        });
        Schema::create('disposition_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('arsip_id');
            $table->string('action');
            $table->text('reason');
            $table->string('status');
            $table->unsignedInteger('requested_by');
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->text('reviewer_note')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('minutes_number', 100)->nullable();
            $table->text('minutes_note')->nullable();
            $table->unsignedInteger('executed_by')->nullable();
            $table->date('execution_date')->nullable();
            $table->string('destination', 255)->nullable();
            $table->string('handover_recipient', 255)->nullable();
            $table->string('evidence_path', 500)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
        Schema::create('duplicate_reviews', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('archive_id');
            $table->unsignedInteger('candidate_id');
            $table->string('match_reason', 100);
            $table->string('status');
            $table->text('reviewer_note')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->unique(['archive_id', 'candidate_id']);
        });
        Schema::create('archive_distributions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('archive_id');
            $table->string('origin_division', 150);
            $table->string('destination_division', 150);
            $table->unsignedInteger('requested_by');
            $table->unsignedInteger('sender_id')->nullable();
            $table->unsignedInteger('recipient_id');
            $table->unsignedInteger('approved_by')->nullable();
            $table->unsignedInteger('dispatched_by')->nullable();
            $table->unsignedInteger('received_by')->nullable();
            $table->string('status', 40);
            $table->text('notes')->nullable();
            $table->text('rejection_note')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->text('receipt_note')->nullable();
            $table->string('receipt_attachment', 500)->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function test_retention_records_distinct_transfer_and_permanent_actions_without_deleting_archives(): void
    {
        Storage::fake('local');
        $requester = $this->createUser('Pengusul Retensi', 'retensi-pengusul', 'Petugas Arsip', 'Keuangan');
        $executor = $this->createUser('Pelaksana Retensi', 'retensi-pelaksana', 'Admin', 'Keuangan');
        $actions = [
            ['Pindahkan Arsip Inaktif', 'Gudang arsip inaktif lantai 2', null, 'Nonaktif'],
            ['Permanen', 'Depo arsip permanen', null, 'Tersimpan'],
            ['Serah ke ANRI', 'Depo ANRI Jakarta', 'Petugas penerima ANRI', 'Nonaktif'],
        ];

        foreach ($actions as $index => [$action, $destination, $recipient, $expectedArchiveStatus]) {
            $archiveId = $this->insertArchive([
                'id' => $index + 1,
                'classification' => 'Internal',
                'division' => 'Keuangan',
                'nama_berkas' => 'Retensi '.$action,
                'qr_code' => sprintf('ARSIP-2026-04-%06d', $index + 1),
            ]);
            $dispositionId = DB::table('disposition_requests')->insertGetId([
                'arsip_id' => $archiveId,
                'action' => $action,
                'reason' => 'Masa retensi telah berakhir.',
                'status' => 'Disetujui',
                'requested_by' => $requester->id,
                'reviewed_by' => $this->admin->id,
                'reviewed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $payload = [
                'minutes_number' => 'BA-RET-'.$index,
                'minutes_note' => 'Pelaksanaan terverifikasi.',
                'execution_date' => now()->toDateString(),
                'evidence' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti"),
            ];
            if ($destination !== null) {
                $payload['destination'] = $destination;
            }
            if ($recipient !== null) {
                $payload['handover_recipient'] = $recipient;
            }

            $this->actingAs($executor)->patch(route('retention.dispositions.complete', $dispositionId), $payload)
                ->assertRedirect(route('retention.minutes', $dispositionId));
            $this->assertDatabaseHas('disposition_requests', [
                'id' => $dispositionId,
                'action' => $action,
                'status' => 'Dilaksanakan',
                'executed_by' => $executor->id,
                'execution_date' => now()->toDateString(),
                'destination' => $destination,
                'handover_recipient' => $recipient,
            ]);
            $this->assertDatabaseHas('arsip', [
                'id' => $archiveId,
                'status' => $expectedArchiveStatus,
            ]);
        }

        $this->assertSame(3, DB::table('arsip')->count());
    }

    public function test_anri_handover_requires_destination_recipient_and_evidence(): void
    {
        Storage::fake('local');
        $requester = $this->createUser('Pengusul', 'retensi-anri-pengusul', 'Petugas Arsip', 'Keuangan');
        $executor = $this->createUser('Pelaksana', 'retensi-anri-pelaksana', 'Admin', 'Keuangan');
        $archiveId = $this->insertArchive(['classification' => 'Internal', 'division' => 'Keuangan']);
        $dispositionId = DB::table('disposition_requests')->insertGetId([
            'arsip_id' => $archiveId,
            'action' => 'Serah ke ANRI',
            'reason' => 'Penyerahan arsip permanen.',
            'status' => 'Disetujui',
            'requested_by' => $requester->id,
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($executor)->from(route('retention.index'))
            ->patch(route('retention.dispositions.complete', $dispositionId), [
                'minutes_number' => 'BA-ANRI-VALIDASI',
                'minutes_note' => 'Keterangan penyerahan.',
                'execution_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('retention.index'))
            ->assertSessionHasErrors(['destination', 'handover_recipient', 'evidence']);
        $this->assertDatabaseHas('disposition_requests', [
            'id' => $dispositionId,
            'status' => 'Disetujui',
            'completed_at' => null,
        ]);
    }

    public function test_retention_cannot_be_executed_before_approval(): void
    {
        Storage::fake('local');
        $requester = $this->createUser('Pengusul Belum Disetujui', 'retensi-belum-setuju', 'Petugas Arsip', 'Keuangan');
        $executor = $this->createUser('Pelaksana Belum Disetujui', 'retensi-pelaksana-belum-setuju', 'Admin', 'Keuangan');
        $archiveId = $this->insertArchive(['classification' => 'Internal', 'division' => 'Keuangan']);
        $dispositionId = DB::table('disposition_requests')->insertGetId([
            'arsip_id' => $archiveId,
            'action' => 'Musnahkan',
            'reason' => 'Masa retensi selesai.',
            'status' => 'Menunggu Persetujuan',
            'requested_by' => $requester->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($executor)->patch(route('retention.dispositions.complete', $dispositionId), [
            'minutes_number' => 'BA-BELUM-SETUJU',
            'minutes_note' => 'Pelaksanaan tidak boleh dilakukan.',
            'execution_date' => now()->toDateString(),
            'evidence' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti"),
        ])->assertStatus(409);

        $this->assertSame([], Storage::disk('local')->allFiles('retention-evidence'));
        $this->assertDatabaseHas('disposition_requests', [
            'id' => $dispositionId,
            'status' => 'Menunggu Persetujuan',
            'completed_at' => null,
        ]);
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Tersimpan']);
    }

    public function test_retention_evidence_is_removed_when_completion_transaction_fails(): void
    {
        Storage::fake('local');
        $requester = $this->createUser('Pengusul Rollback', 'retensi-rollback-pengusul', 'Petugas Arsip', 'Keuangan');
        $executor = $this->createUser('Pelaksana Rollback', 'retensi-rollback-pelaksana', 'Admin', 'Keuangan');
        $archiveId = $this->insertArchive(['classification' => 'Internal', 'division' => 'Keuangan']);
        $dispositionId = DB::table('disposition_requests')->insertGetId([
            'arsip_id' => $archiveId,
            'action' => 'Musnahkan',
            'reason' => 'Masa retensi selesai.',
            'status' => 'Disetujui',
            'requested_by' => $requester->id,
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("CREATE TRIGGER fail_retention_completion BEFORE UPDATE ON disposition_requests BEGIN SELECT RAISE(ABORT, 'forced failure'); END");
        $this->actingAs($executor)->patch(route('retention.dispositions.complete', $dispositionId), [
            'minutes_number' => 'BA-ROLLBACK',
            'minutes_note' => 'Pelaksanaan batal.',
            'execution_date' => now()->toDateString(),
            'evidence' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti"),
        ])->assertInternalServerError();

        $this->assertSame([], Storage::disk('local')->allFiles('retention-evidence'));
        $this->assertDatabaseHas('disposition_requests', [
            'id' => $dispositionId,
            'status' => 'Disetujui',
            'evidence_path' => null,
        ]);
        $this->assertDatabaseHas('arsip', ['id' => $archiveId, 'status' => 'Tersimpan']);
    }
}
