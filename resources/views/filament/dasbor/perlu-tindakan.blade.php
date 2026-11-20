<x-filament-widgets::widget>
    <section aria-labelledby="perlu-tindakan-judul" class="sigap-panel-kartu">
        <h2 id="perlu-tindakan-judul" class="sigap-panel-kartu-judul">Perlu tindakan Anda</h2>

        @foreach ($bagian as $item)
            <div class="sigap-tindakan-bagian">
                <div class="sigap-tindakan-kepala">
                    <h3>{{ $item['judul'] }}</h3>
                    <a href="{{ $item['url'] }}" class="sigap-tautan">Lihat semua
                        <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"></path></svg>
                    </a>
                </div>

                @if ($item['baris'] === [])
                    <p class="sigap-tindakan-kosong">{{ $item['kosong'] }}</p>
                @else
                    <ul class="sigap-tindakan-daftar">
                        @foreach ($item['baris'] as $baris)
                            <li>
                                <a href="{{ $baris['url'] }}" class="sigap-tindakan-baris">
                                    <span class="sigap-tindakan-atas">
                                        <span class="sigap-tindakan-kode">{{ $baris['kode'] }}</span>
                                        <x-filament::badge :color="$baris['warna']">{{ $baris['status'] }}</x-filament::badge>
                                    </span>
                                    <span class="sigap-tindakan-judul">{{ $baris['judul'] }}</span>
                                    <span class="sigap-tindakan-bawah">
                                        <span>{{ $baris['keterangan'] }}</span>
                                        @if ($baris['batas'])
                                            <span @class(['sigap-lewat' => $baris['lewat']])>Batas: {{ $baris['batas'] }}@if ($baris['lewat']) (lewat)@endif</span>
                                        @endif
                                    </span>
                                    <svg aria-hidden="true" class="sigap-panah" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"></path></svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </section>
</x-filament-widgets::widget>
