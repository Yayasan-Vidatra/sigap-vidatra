<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

<!-- Bagian di bawah ini milik proyek SIGAP Vidatra dan berada di luar blok Laravel Boost agar tidak tertimpa saat `boost:install` atau `boost:update`. -->

# SIGAP Vidatra — aturan proyek

SIGAP Vidatra (sigap.vidatra.sch.id) adalah website Pengaduan dan Pelaporan Pelanggaran (WBS) Yayasan Vidatra.

## Dokumen acuan

Sebelum menulis atau mengubah kode, baca bagian yang relevan dari dokumen di `prd/` (indeks dan urutan prioritas ada di `prd/README.md`):

- `prd/01-rancangan-sistem-r1.md` — aturan bisnis, aktor, matriks penerima (Bagian 3.1), use case UC-xx, status laporan (Bagian 6), kebutuhan F-xx, lampiran (Bagian 8), keamanan (Bagian 9), rancangan data (Bagian 10).
- `prd/02-tor.md` — halaman H-01 sampai H-20 dan fiturnya, cakupan Tahap 1/2, infrastruktur, timeline 15 hari, kriteria penerimaan.
- `prd/03-prd.md` — user story US-xx dengan kriteria penerimaan, NF-xx, rilis, matriks keterlacakan.

Setiap pekerjaan dimulai dengan menyebut kode US yang dikerjakan beserta UC/F/H terkait. Pekerjaan selesai bila kriteria penerimaan US itu terpenuhi dan bisa ditunjukkan, bukan hanya kodenya ada.

Bila ada perbedaan antar dokumen, ikuti urutan: Rancangan r1 > PRD > TOR. Bila dokumen tidak menjawab atau saling bertentangan, berhenti dan tanya, jangan menebak.

## Stack dan struktur

- Laravel 13, PHP 8.3, MySQL. Tailwind 4, Vite 8. Paket utama: Inertia v3, Filament 5, Laravel Socialite (driver google), Laravel Boost (dev).
- **Halaman publik H-01 sampai H-08**: React 19 lewat Inertia v3. Halaman di `resources/js/pages/`, layout publik `resources/js/layouts/PublicLayout.jsx`, route di `routes/web.php` dengan `Route::inertia()` atau controller yang mengembalikan `Inertia::render()`. Tidak memakai React Router.
- **Panel petugas H-09 sampai H-20**: Filament 5, panel ID `panel` di `/panel`.
- Nama tabel dan kolom mengikuti Rancangan r1 Bagian 10 (`laporan`, `lampiran`, `identitas_pelapor`, `disposisi`, `pengalihan`, dst.).
- Teks antarmuka berbahasa Indonesia yang sederhana.

## Aturan yang tidak boleh dilanggar

1. **Hak akses ditegakkan di server** (policy/gate Laravel dan query scope), tidak cukup dengan menyembunyikan tombol di React atau Filament.
2. **Matriks penerima WBS** (Rancangan Bagian 3.1): laporan dengan kedudukan terlapor SPI/anggota Tim WBS, Pengawas, atau Pembina tidak boleh bisa dibuka Ketua Tim WBS.
3. **Super Admin tidak dapat membaca isi WBS.** PIC Unit hanya melihat pengaduan unitnya. Kepala Sekolah tidak melihat laporan yang menyangkut dirinya.
4. **Data WBS terenkripsi**: identitas pelapor, terlapor, catatan internal, dan lampiran WBS dienkripsi saat disimpan.
5. **Tanpa jejak pelapor WBS**: jalur `/wbs*` tidak mencatat IP, tidak memasang cookie pelacak, dan tidak memuat analitik pihak ketiga.
6. **Log audit hanya bisa ditambah**: tidak ada fitur atau query untuk mengubah atau menghapus baris log audit.
7. **Data jabatan tidak ditanam di kode**: nama dan surel Admin, PIC, Kepala Sekolah, Komite Etik, serta kontak darurat disimpan di basis data dan dikelola lewat Pengaturan (H-19). Unit: SD YPVDP, SMP YPVDP, SMA YPVDP, Pelaksana Harian.
8. **Lampiran** disimpan di disk privat dengan nama acak, diunduh lewat tautan sementara, dan dibatasi sesuai Rancangan Bagian 8.
9. **Jangan mengerjakan fitur Tahap 2** (US-28 sampai US-31, H-15) atau fitur di luar cakupan kecuali diminta secara eksplisit.

## Deviasi dari rancangan

Bila implementasi harus berbeda dari dokumen acuan (teknis tidak memungkinkan, ada cara yang jelas lebih baik, atau dokumen keliru), **jangan langsung mengubahnya**. Jelaskan perbedaannya, tambahkan baris berstatus `Diusulkan` di `prd/log-deviasi.md`, lalu tunggu persetujuan. Jangan mengubah isi `prd/01`–`03` kecuali diminta.

## Alur kerja

- Branch: `feat/US-xx-ringkasan` (contoh `feat/US-02-formulir-pengaduan`); untuk persiapan atau perbaikan: `setup/...`, `fix/...`, `docs/...`.
- Pesan commit menyebut kode, contoh `feat(US-02): formulir pengaduan dengan draf otomatis`.
- Deskripsi pull request memuat: kode US/UC/F/H yang dikerjakan, cara menguji kriteria penerimaannya, dan butir daftar periksa PRD Bagian 7 yang relevan.
- Di akhir setiap tugas, laporkan US yang tersentuh dan usulan statusnya (`Dikerjakan`, `Selesai, belum dicek`, atau `Ada deviasi`) agar matriks keterlacakan di PRD online bisa diperbarui.
- Jangan mengubah `.env` dan jangan meng-commit rahasia (kunci, kata sandi, token).
