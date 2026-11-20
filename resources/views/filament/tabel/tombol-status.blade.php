{{-- Tombol status dengan jumlah (prototipe prd/mockup/panel-*.html). Mengisi filter tabel "status". --}}
<div role="group" aria-label="Saring status" class="sigap-tombol-status">
    @foreach ($tombol as $item)
        <button
            type="button"
            wire:click="$set('tableFilters.status.value', @js($item['nilai']))"
            aria-pressed="{{ $item['aktif'] ? 'true' : 'false' }}"
            @class(['sigap-tombol-status-item', 'sigap-aktif' => $item['aktif']])
        >
            {{ $item['label'] }}
            <span class="sigap-tombol-status-jumlah">{{ $item['jumlah'] }}</span>
        </button>
    @endforeach
</div>
