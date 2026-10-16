<div>
    @if ($canViewStats)
        @include('livewire.pages.dashboard-stats')
    @else
        @include('livewire.pages.dashboard-welcome')
    @endif
</div>
