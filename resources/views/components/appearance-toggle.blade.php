@props(['class' => ''])

{{-- Light/Dark toggle. Sun in dark mode (switch to light), moon in light mode (switch to dark).
     Uses $flux.dark (Flux's Alpine helper) so localStorage + .dark class stay in sync. --}}
<flux:button
    x-data
    x-on:click="$flux.dark = ! $flux.dark"
    variant="subtle"
    square
    aria-label="{{ __('Erscheinungsbild umschalten') }}"
    :class="$class"
>
    <flux:icon.sun x-show="$flux.dark" x-cloak variant="mini" class="text-stage-text-muted" />
    <flux:icon.moon x-show="! $flux.dark" x-cloak variant="mini" class="text-stage-text-muted" />
</flux:button>
