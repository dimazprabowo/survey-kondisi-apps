<x-app-layout title="Edit Word — {{ $survey->report?->report_title ?? 'Laporan Survey' }}">
    <livewire:surveys.report-word-editor :survey="$survey" />
</x-app-layout>
