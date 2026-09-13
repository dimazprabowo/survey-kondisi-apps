<x-app-layout title="Detail Template Form">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Detail Template Form
        </h2>
    </x-slot>

    <livewire:templates.template-show :template="$template" />
</x-app-layout>
