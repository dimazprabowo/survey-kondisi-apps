<?php

namespace App\Livewire\Traits;

trait HasTableSorting
{
    /**
     * Kolom sort aktif — set default-nya di mount() component.
     * Whitelist kolom ada di Service (parameter $sortable getFiltered).
     */
    public string $sortField = '';

    public string $sortDir = 'asc';

    /**
     * Toggle sorting kolom: klik kolom sama = balik arah, kolom baru = asc.
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }
}
