<x-filament-widgets::widget>
    <section aria-label="Ringkasan" class="sigap-kartu-ringkasan">
        @foreach ($kartu as $item)
            @php($tag = $item['url'] ? 'a' : 'div')
            <{{ $tag }}
                @if ($item['url']) href="{{ $item['url'] }}" @endif
                @class(['sigap-kartu', 'sigap-kartu-tautan' => $item['url'], 'sigap-kartu-peringatan' => $item['peringatan']])
            >
                <span class="sigap-kartu-label">{{ $item['label'] }}</span>
                <span class="sigap-kartu-nilai">{{ $item['nilai'] }}</span>
                <span class="sigap-kartu-keterangan">{{ $item['keterangan'] }}</span>
                @if ($item['url'])
                    <span class="sigap-kartu-buka">Buka daftar
                        <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"></path></svg>
                    </span>
                @endif
            </{{ $tag }}>
        @endforeach
    </section>
</x-filament-widgets::widget>
