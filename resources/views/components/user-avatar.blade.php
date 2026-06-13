@props(['user'])

@if ($user->isAdmin())
    <flux:avatar {{ $attributes }} :alt="$user->name">
        <x-app-logo-icon class="size-5" />
    </flux:avatar>
@else
    <flux:avatar {{ $attributes }} :name="$user->name" :initials="$user->initials()" />
@endif
