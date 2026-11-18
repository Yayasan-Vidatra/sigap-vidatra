<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 text-left dark:border-white/10">
                <th class="py-2 pe-4 font-medium">Kategori</th>
                @foreach ([1, 2, 3, 4] as $triwulan)
                    <th class="py-2 pe-4 text-end font-medium">Triwulan {{ $triwulan }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $item)
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-2 pe-4">{{ $item['kategori'] }}</td>
                    @foreach ($item['triwulan'] as $nilai)
                        <td class="py-2 pe-4 text-end tabular-nums">{{ $nilai ?? '< '.$minimal }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="5" class="py-2 text-gray-500">Belum ada kategori WBS.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
