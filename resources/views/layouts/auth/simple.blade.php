<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stage-bg text-stage-text antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-8 px-6 py-12">
            <a href="{{ route('home') }}" class="flex flex-col items-center gap-1.5" wire:navigate>
                <span class="wordmark text-2xl">pongtable</span>
                <span class="font-label text-stage-text-dim">Referee Access</span>
            </a>

            <div class="w-full max-w-md rounded-lg bg-stage-surface px-8 py-9 lg:px-10 lg:py-10">
                {{ $slot }}
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
