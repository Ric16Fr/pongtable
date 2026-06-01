<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Einstellungen')] class extends Component {
    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public string $newRefereeName = '';

    public string $newRefereePassword = '';

    public ?int $resetUserId = null;

    public string $resetPassword = '';

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'current_password'],
            'newPassword' => ['required', 'confirmed:newPasswordConfirmation', Password::min(8)],
        ], attributes: [
            'currentPassword' => __('aktuelles Passwort'),
            'newPassword' => __('neues Passwort'),
        ]);

        auth()->user()->update(['password' => $this->newPassword]);

        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);

        Flux::toast(variant: 'success', text: __('Passwort geändert.'));
    }

    public function createReferee(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->validate([
            'newRefereeName' => ['required', 'string', 'max:255', 'unique:users,name'],
            'newRefereePassword' => ['required', Password::min(8)],
        ], attributes: [
            'newRefereeName' => __('Benutzername'),
            'newRefereePassword' => __('Passwort'),
        ]);

        User::create([
            'name' => $this->newRefereeName,
            'password' => $this->newRefereePassword,
            'role' => 'referee',
        ]);

        $this->reset(['newRefereeName', 'newRefereePassword']);
        unset($this->referees);

        Flux::toast(variant: 'success', text: __('Schiri angelegt.'));
    }

    public function deleteReferee(int $userId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $user = User::findOrFail($userId);

        abort_if($user->isAdmin(), 422);

        $user->delete();
        unset($this->referees);

        Flux::toast(variant: 'success', text: __('Schiri gelöscht.'));
    }

    public function startReset(int $userId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $user = User::findOrFail($userId);
        abort_if($user->isAdmin(), 422);

        $this->resetUserId = $userId;
        $this->resetPassword = '';
        $this->resetErrorBag('resetPassword');

        Flux::modal('reset-password')->show();
    }

    public function resetUserPassword(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->validate([
            'resetPassword' => ['required', Password::min(8)],
        ], attributes: [
            'resetPassword' => __('neues Passwort'),
        ]);

        $user = User::findOrFail($this->resetUserId);
        abort_if($user->isAdmin(), 422);

        $user->update(['password' => $this->resetPassword]);

        $this->reset(['resetUserId', 'resetPassword']);

        Flux::modal('reset-password')->close();
        Flux::toast(variant: 'success', text: __('Passwort zurückgesetzt.'));
    }

    #[Computed]
    public function referees()
    {
        return User::where('role', 'referee')->orderBy('name')->get();
    }

    #[Computed]
    public function resetUser(): ?User
    {
        return $this->resetUserId ? User::find($this->resetUserId) : null;
    }
}; ?>

<div>
    <div class="mx-auto w-full max-w-3xl space-y-12 p-4 lg:p-6">

        {{-- Title --}}
        <header class="flex flex-col gap-4">
            <div class="font-label flex items-center gap-3 text-trophy-gold">
                <span class="block h-px w-12 bg-trophy-gold"></span>
                <span>{{ auth()->user()->isAdmin() ? __('Administrator') : __('Schiedsrichter') }}</span>
            </div>
            <h1 class="font-display text-stage-text text-[clamp(2rem,5vw,3.5rem)]">{{ __('Einstellungen') }}</h1>
        </header>

        {{-- Change own password --}}
        <section class="space-y-5">
            <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                <h2 class="font-display text-2xl text-stage-text">{{ __('Passwort ändern') }}</h2>
                <span class="font-label text-stage-text-dim">{{ auth()->user()->name }}</span>
            </div>

            <form wire:submit="changePassword" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <flux:input
                        wire:model="currentPassword"
                        type="password"
                        :label="__('Aktuelles Passwort')"
                        autocomplete="current-password"
                        viewable
                    />
                </div>
                <flux:input
                    wire:model="newPassword"
                    type="password"
                    :label="__('Neues Passwort')"
                    autocomplete="new-password"
                    viewable
                />
                <flux:input
                    wire:model="newPasswordConfirmation"
                    type="password"
                    :label="__('Neues Passwort (Wiederholung)')"
                    autocomplete="new-password"
                    viewable
                />
                <div class="md:col-span-2">
                    <flux:button type="submit" variant="primary" data-test="change-password-button">
                        {{ __('Speichern') }}
                    </flux:button>
                </div>
            </form>
        </section>

        @if (auth()->user()->isAdmin())
            {{-- Add referee --}}
            <section class="space-y-5">
                <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                    <h2 class="font-display text-2xl text-stage-text">{{ __('Schiri anlegen') }}</h2>
                    <span class="font-label text-stage-text-dim">{{ __('Neuer Schiedsrichter-Account') }}</span>
                </div>

                <form wire:submit="createReferee" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <flux:input
                        wire:model="newRefereeName"
                        :label="__('Benutzername')"
                        placeholder="z.B. ref3"
                        autocomplete="off"
                    />
                    <flux:input
                        wire:model="newRefereePassword"
                        type="password"
                        :label="__('Passwort')"
                        autocomplete="new-password"
                        viewable
                    />
                    <div class="md:col-span-2">
                        <flux:button type="submit" variant="primary" data-test="create-referee-button">
                            {{ __('Anlegen') }}
                        </flux:button>
                    </div>
                </form>
            </section>

            {{-- Manage referees --}}
            <section class="space-y-5">
                <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                    <h2 class="font-display text-2xl text-stage-text">{{ __('Schiedsrichter') }}</h2>
                    <span class="font-numeric text-2xl font-bold text-stage-text">{{ $this->referees->count() }}</span>
                </div>

                @if ($this->referees->isEmpty())
                    <p class="text-sm text-stage-text-dim">{{ __('Noch keine Schiedsrichter angelegt.') }}</p>
                @else
                    <ul class="grid grid-cols-1 gap-2">
                        @foreach ($this->referees as $referee)
                            <li class="flex items-center justify-between rounded-md bg-stage-surface px-4 py-3">
                                <span class="font-medium text-stage-text">{{ $referee->name }}</span>
                                <div class="flex items-center gap-3">
                                    <button
                                        wire:click="startReset({{ $referee->id }})"
                                        class="text-xs font-semibold text-stage-text hover:underline"
                                        data-test="reset-button-{{ $referee->id }}"
                                    >
                                        {{ __('Passwort zurücksetzen') }}
                                    </button>
                                    <button
                                        wire:click="deleteReferee({{ $referee->id }})"
                                        wire:confirm="{{ __('Schiri wirklich löschen?') }}"
                                        class="text-xs font-semibold text-status-danger hover:underline"
                                        data-test="delete-button-{{ $referee->id }}"
                                    >
                                        {{ __('Entfernen') }}
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <flux:modal name="reset-password" class="max-w-md">
                <form wire:submit="resetUserPassword" class="space-y-5">
                    <div>
                        <flux:heading size="lg">{{ __('Passwort zurücksetzen') }}</flux:heading>
                        @if ($this->resetUser)
                            <flux:text class="mt-2">
                                {{ __('Neues Passwort für :name vergeben.', ['name' => $this->resetUser->name]) }}
                            </flux:text>
                        @endif
                    </div>
                    <flux:input
                        wire:model="resetPassword"
                        type="password"
                        :label="__('Neues Passwort')"
                        autocomplete="new-password"
                        viewable
                    />
                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost">{{ __('Abbrechen') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary" data-test="confirm-reset-button">
                            {{ __('Zurücksetzen') }}
                        </flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif
    </div>
</div>
