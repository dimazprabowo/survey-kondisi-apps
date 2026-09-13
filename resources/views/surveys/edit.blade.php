<x-app-layout title="Edit Survey Kondisi">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Edit Survey Kondisi
        </h2>
    </x-slot>

    <livewire:surveys.survey-form :survey="$survey" />
</x-app-layout>
