<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stage-bg text-stage-text">
        <flux:sidebar sticky collapsible="mobile" class="w-72 border-e border-stage-line bg-stage-surface">
            <flux:sidebar.header>
                <div class="flex w-full items-start justify-between gap-2">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex flex-col items-start gap-0.5 px-2 py-4">
                        <span class="wordmark text-lg">pongtable</span>
                        <span class="font-label text-stage-text-dim">{{ __('Schiri-Verwaltung') }}</span>
                    </a>
                    <div class="flex items-center pt-3">
                        <x-appearance-toggle />
                        <flux:sidebar.collapse class="lg:hidden" />
                    </div>
                </div>
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Turnier')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="play" :href="route('matches.index')" :current="request()->routeIs('matches.*') || request()->routeIs('match.*')" wire:navigate>
                        {{ __('Matches') }}
                    </flux:sidebar.item>
                    @if (auth()->user()?->isAdmin())
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('tournament.setup')" :current="request()->routeIs('tournament.setup')" wire:navigate>
                            {{ __('Setup') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" :href="route('statistics')" :current="request()->routeIs('statistics')" wire:navigate>
                            {{ __('Statistik') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item :href="route('special-rules')" :current="request()->routeIs('special-rules')" wire:navigate>
                            <x-slot:icon>
                                <span class="flex size-4 items-center justify-center text-base leading-none" aria-hidden="true">
                                    <svg class="w-64 h-64" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M9 3C10.1046 3 11 3.89543 11 5C11 5.11469 10.9904 5.22639 10.9723 5.33454L12.9447 5.66546C12.9812 5.4484 13 5.22602 13 5C13 4.7625 12.9793 4.52984 12.9396 4.30371C13.2472 4.11088 13.6103 4 14 4C15.1046 4 16 4.89543 16 6C16 6.36443 15.903 6.70571 15.7327 7H5C4.44772 7 4 6.55228 4 6C4 5.44772 4.44772 5 5 5C5.20008 5 5.38362 5.05773 5.53851 5.15709C5.81193 5.33249 6.15353 5.36415 6.45453 5.24199C6.75554 5.11982 6.97845 4.85905 7.05229 4.5427C7.25876 3.65813 8.05374 3 9 3ZM10.5164 1.29745C10.0489 1.10575 9.53693 1 9 1C7.50087 1 6.19573 1.82409 5.51068 3.04344C5.34453 3.01488 5.17387 3 5 3C3.34315 3 2 4.34315 2 6C2 6.8885 2.38625 7.68679 3 8.23611V20C3 21.1046 3.89543 22 5 22H15C16.1046 22 17 21.1046 17 20H19C20.1046 20 21 19.1046 21 18V11C21 9.89543 20.1046 9 19 9H17V8.64575C17.6215 7.94132 18 7.01438 18 6C18 3.79086 16.2091 2 14 2C13.3143 2 12.6684 2.17301 12.1042 2.47716C11.6851 1.96201 11.1402 1.5532 10.5164 1.29745ZM17 11H19V18H17V11ZM15 9V10V19V20H5V9H15ZM7 11V18H9V11H7ZM13 11V18H11V11H13Z"></path></svg>
                                </span>
                            </x-slot:icon>
                            {{ __('Sonderregeln & Einstellungen') }}
                        </flux:sidebar.item>
                        @if (\App\Models\Tournament::count() > 1)
                            <flux:sidebar.item icon="archive-box" :href="route('archive.index')" :current="request()->routeIs('archive.*')" wire:navigate>
                                {{ __('Archiv') }}
                            </flux:sidebar.item>
                        @endif
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:dropdown position="bottom" align="start" class="hidden lg:block">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-up"
                />

                <flux:menu>
                    <div class="px-2 py-1.5 text-xs text-stage-text-muted">
                        {{ auth()->user()->isAdmin() ? __('Administrator') : __('Schiedsrichter') }}
                    </div>
                    <flux:menu.separator />
                    <flux:menu.item
                        :href="route('settings')"
                        icon="cog-6-tooth"
                        wire:navigate
                        data-test="settings-link"
                    >
                        {{ __('Einstellungen') }}
                    </flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Abmelden') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <flux:header class="lg:hidden border-b border-stage-line bg-stage-surface">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <x-appearance-toggle class="mr-1" />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate text-xs">
                                        {{ auth()->user()->isAdmin() ? __('Administrator') : __('Schiedsrichter') }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.item
                        :href="route('settings')"
                        icon="cog-6-tooth"
                        wire:navigate
                    >
                        {{ __('Einstellungen') }}
                    </flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Abmelden') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
