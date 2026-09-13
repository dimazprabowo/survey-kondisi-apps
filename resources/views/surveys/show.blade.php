<x-app-layout title="Detail Survey Kondisi">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Detail Survey Kondisi
        </h2>
    </x-slot>

    <livewire:surveys.survey-show :survey="$survey" />
</x-app-layout>
