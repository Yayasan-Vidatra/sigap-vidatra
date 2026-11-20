<div class="sigap-gulir-tabel">
    <table class="sigap-tabel-rekap">
        <colgroup>
            <col>
            @foreach ([1, 2, 3, 4] as $triwulan)
                <col style="width: 15%">
            @endforeach
        </colgroup>
        <thead>
            <tr>
                <th scope="col">Kategori</th>
                @foreach ([1, 2, 3, 4] as $triwulan)
                    <th scope="col" class="sigap-angka">Triwulan {{ $triwulan }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $item)
                <tr>
                    <td>{{ $item['kategori'] }}</td>
                    @foreach ($item['triwulan'] as $nilai)
                        <td class="sigap-angka">{{ $nilai ?? '< '.$minimal }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="5" class="sigap-kosong">Belum ada kategori WBS.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
