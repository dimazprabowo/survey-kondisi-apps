<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait SortsQuery
{
    /**
     * Terapkan sorting kolom ke query berdasarkan whitelist $sortable
     * (key = nama field dari UI, value = kolom/ekspresi SQL).
     *
     * Nilai NULL dianggap terkecil: paling atas saat asc, paling bawah saat
     * desc. Field tidak dikenal -> fallback ke $defaultField + $defaultDir.
     * Tie-break via $tieBreakColumn menjaga urutan deterministik antar-halaman.
     */
    protected function applySorting(
        Builder $query,
        array $sortable,
        ?string $sortField,
        string $sortDir,
        string $defaultField,
        string $defaultDir = 'asc',
        string $tieBreakColumn = 'id',
        string $tieBreakDir = 'desc'
    ): void {
        $dir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        if (! isset($sortable[$sortField])) {
            $sortField = $defaultField;
            $dir = $defaultDir;
        }

        $expr = $sortable[$sortField];
        $nullDir = $dir === 'asc' ? 'DESC' : 'ASC';

        $query->orderByRaw("$expr IS NULL $nullDir")
            ->orderByRaw("$expr $dir")
            ->orderBy($tieBreakColumn, $tieBreakDir);
    }
}
