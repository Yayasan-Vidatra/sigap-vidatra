{{--
    Tautan Google Drive dari pelapor (revisi 20-11). Dibuka lewat tautan bertanda tangan yang memeriksa hak akses
    dan mencatat log audit; tab baru tanpa referrer dan tanpa akses ke halaman panel (noopener noreferrer).
--}}
<ul class="sigap-tautan-bukti">
    @foreach ($getState() ?? [] as $tautan)
        <li>
            <span class="sigap-tautan-bukti-label">Tautan luar, tidak dipindai</span>
            <a href="{{ $tautan['buka'] }}" target="_blank" rel="noopener noreferrer" class="sigap-tautan">{{ \Illuminate\Support\Str::limit($tautan['url'], 90) }}</a>
            <span class="sigap-tautan-bukti-waktu">{{ $tautan['dariPesan'] ? 'Dari pesan pelapor' : 'Dari laporan' }} · {{ $tautan['waktu'] }}</span>
        </li>
    @endforeach
</ul>
