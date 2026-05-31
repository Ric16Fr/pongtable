<x-layouts::auth :title="__('Login')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Anmelden')" :description="__('Benutzername und Passwort eingeben')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="name"
                :label="__('Benutzername')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="z.B. admin"
            />

            <flux:input
                name="password"
                :label="__('Passwort')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Passwort')"
                viewable
            />

            <flux:checkbox name="remember" :label="__('Eingeloggt bleiben')" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                {{ __('Anmelden') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
