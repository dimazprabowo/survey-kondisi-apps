@props([
    'score' => null,
    'label' => 'Overall CAP Rating',
    'description' => 'Rata-rata dari semua kategori (mirip Excel AVERAGE)',
])

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
    <div class="px-4 py-4 sm:px-6 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $label }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        </div>
        @if($score !== null)
            <span class="inline-flex items-center px-4 py-2 rounded-full text-lg font-bold {{ survey_score_badge_class($score) }}">
                {{ number_format($score, 2) }}
            </span>
        @else
            <span class="text-gray-400 dark:text-gray-500 text-sm">Belum ada skor</span>
        @endif
    </div>
</div>
