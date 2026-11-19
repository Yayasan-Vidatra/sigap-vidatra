<?php

namespace Tests\Feature\Filament\Pages;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Filament\Pages\KontenPublik;
use App\Models\LogAudit;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\Pengaturan\PengelolaKontenPublik;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KontenPublikTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();
    }

    public function test_an_uploaded_guarantee_file_replaces_the_old_one_and_is_served_at_wbs_right_away(): void
    {
        Storage::disk('local')->put('pengaturan/lama.pdf', '%PDF-1.4 berkas lama');
        Pengaturan::factory()->create(['kunci' => Pengaturan::WBS_BERKAS_JAMINAN, 'nilai' => 'pengaturan/lama.pdf']);

        $this->actingAs($this->superAdmin);

        Livewire::test(KontenPublik::class)
            ->assertFormSet(fn (array $state): bool => array_values($state['berkas_jaminan']) === ['pengaturan/lama.pdf'])
            ->fillForm(['berkas_jaminan' => []])
            ->fillForm(['berkas_jaminan' => UploadedFile::fake()->createWithContent('Jaminan Kerahasiaan.pdf', "%PDF-1.4\nberkas baru\n%%EOF")])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $path = Pengaturan::nilai(Pengaturan::WBS_BERKAS_JAMINAN);
        $this->assertMatchesRegularExpression('#\Apengaturan/[A-Za-z0-9]{40}\.pdf\z#', $path);
        $this->assertStringNotContainsString('Jaminan', $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('local')->assertMissing('pengaturan/lama.pdf');
        Storage::disk('public')->assertMissing($path);

        $berkas = $this->get('/wbs/jaminan-kerahasiaan');
        $berkas->assertOk();
        $this->assertStringContainsString('berkas baru', $berkas->streamedContent());
        $this->get('/wbs')->assertOk()->assertInertia(fn ($page) => $page->where('berkasJaminan', route('wbs.jaminan.berkas')));

        $log = LogAudit::query()->where('objek', 'pengaturan:'.Pengaturan::WBS_BERKAS_JAMINAN)->sole();
        $this->assertSame(AksiAudit::MengubahPengaturan->value, $log->aksi);
        $this->assertSame(['nilai', 'pengaturan/lama.pdf', $path], [
            $log->riwayatPengaturan->sole()->kolom, $log->riwayatPengaturan->sole()->nilai_lama, $log->riwayatPengaturan->sole()->nilai_baru,
        ]);
    }

    public function test_a_non_pdf_file_is_rejected(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(KontenPublik::class)
            ->fillForm(['berkas_jaminan' => UploadedFile::fake()->createWithContent('jaminan.pdf', '<html>bukan pdf</html>')])
            ->call('simpan')
            ->assertHasFormErrors(['berkas_jaminan']);

        $this->assertNull(Pengaturan::nilai(Pengaturan::WBS_BERKAS_JAMINAN));
    }

    public function test_a_path_outside_the_settings_folder_cannot_be_published(): void
    {
        Storage::disk('local')->put('lampiran/wbs/bukti.pdf', '%PDF-1.4 bukti rahasia');

        $this->expectException(InvalidArgumentException::class);

        try {
            app(PengelolaKontenPublik::class)->simpan($this->superAdmin, [Pengaturan::WBS_BERKAS_JAMINAN => 'lampiran/wbs/bukti.pdf']);
        } finally {
            $this->assertNull(Pengaturan::nilai(Pengaturan::WBS_BERKAS_JAMINAN));
        }
    }

    public function test_faq_privacy_and_channels_are_saved_and_each_changed_key_is_audited(): void
    {
        Pengaturan::factory()->create(['kunci' => Pengaturan::WBS_SALURAN_SUREL, 'nilai' => 'lama@contoh.test']);

        $this->actingAs($this->superAdmin);

        Livewire::test(KontenPublik::class)
            ->fillForm([
                'saluran_surel' => 'wbs@contoh.test',
                'faq' => [['pertanyaan' => 'Apakah identitas saya aman?', 'jawaban' => 'Ya.']],
                'privasi' => [['judul' => 'Lama penyimpanan', 'isi' => 'Lima tahun setelah laporan ditutup.']],
            ])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $this->assertSame('wbs@contoh.test', Pengaturan::nilai(Pengaturan::WBS_SALURAN_SUREL));
        $this->assertSame([['pertanyaan' => 'Apakah identitas saya aman?', 'jawaban' => 'Ya.']], Pengaturan::daftar(Pengaturan::PUBLIK_FAQ));
        $this->assertSame([['judul' => 'Lama penyimpanan', 'isi' => 'Lima tahun setelah laporan ditutup.']], Pengaturan::daftar(Pengaturan::PUBLIK_PRIVASI));

        $this->assertEqualsCanonicalizing(
            ['pengaturan:'.Pengaturan::WBS_SALURAN_SUREL, 'pengaturan:'.Pengaturan::PUBLIK_FAQ, 'pengaturan:'.Pengaturan::PUBLIK_PRIVASI],
            LogAudit::query()->where('aksi', AksiAudit::MengubahPengaturan->value)->pluck('objek')->all(),
        );
        $this->assertDatabaseHas('riwayat_pengaturan', ['nilai_lama' => 'lama@contoh.test', 'nilai_baru' => 'wbs@contoh.test']);
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranSelainSuperAdmin(): array
    {
        return collect(Peran::cases())
            ->reject(fn (Peran $peran): bool => $peran === Peran::SuperAdmin)
            ->mapWithKeys(fn (Peran $peran): array => [$peran->value => [$peran]])
            ->all();
    }

    #[DataProvider('peranSelainSuperAdmin')]
    public function test_other_peran_are_forbidden_even_by_direct_url(Peran $peran): void
    {
        $user = User::factory()->peran($peran)->denganMfa()->create();

        $this->actingAs($user)->get('/panel/pengaturan/konten-publik')->assertForbidden();
    }

    public function test_other_peran_cannot_save_through_the_service(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();

        $this->expectException(AuthorizationException::class);

        try {
            app(PengelolaKontenPublik::class)->simpan($admin, [Pengaturan::WBS_SALURAN_SUREL => 'palsu@contoh.test']);
        } finally {
            $this->assertNull(Pengaturan::nilai(Pengaturan::WBS_SALURAN_SUREL));
        }
    }
}
