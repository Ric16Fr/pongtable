<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stage-bg text-stage-text">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-stage-line bg-stage-surface">
            <flux:sidebar.header>
                <a href="{{ route('dashboard') }}" wire:navigate class="flex flex-col items-start gap-0.5 px-2 py-4">
                    <span class="wordmark text-lg">pongtable</span>
                    <span class="font-label text-stage-text-dim">{{ __('Schiri-Verwaltung') }}</span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
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
