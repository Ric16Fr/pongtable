<?php

use App\Models\Tournament;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Sonderregeln & Einstellungen')] class extends Component {
    public ?int $tournamentId = null;

    #[Validate('required|string|max:255')]
    public string $tournamentName = '';

    #[Validate('required|string|max:1000')]
    public string $description = '';

    #[Validate('required|integer|min:1|max:60')]
    public int $groupMinutes = 10;

    #[Validate('required|integer|min:1|max:60')]
    public int $koMinutes = 15;

    public bool $countThrows = true;

    public bool $playPlacementMatches = false;

    #[Validate('required|in:auto,sudden_death')]
    public string $koWinnerMode = 'auto';

    public function mount(): void
    {
        $tournament = Tournament::query()->latest()->first();

        if (! $tournament) {
            $tournament = Tournament::create([
                'name' => 'Bierpong Cup '.now()->year,
                'group_match_duration_minutes' => 10,
                'ko_match_duration_minutes' => 15,
            ]);
        }

        $this->tournamentId = $tournament->id;
        $this->tournamentName = $tournament->name;
        $this->description = $tournament->description ?? Tournament::DEFAULT_DESCRIPTION;
        $this->groupMinutes = $tournament->group_match_duration_minutes;
        $this->koMinutes = $tournament->ko_match_duration_minutes;
        $this->countThrows = $tournament->count_throws ?? true;
        $this->playPlacementMatches = $tournament->play_placement_matches ?? false;
        $this->koWinnerMode = ($tournament->ko_sudden_death ?? false) ? 'sudden_death' : 'auto';
    }

    #[Computed]
    public function tournament(): Tournament
    {
        return Tournament::findOrFail($this->tournamentId);
    }

    public function saveSettings(): void
    {
        $this->validateOnly('tournamentName');
        $this->validateOnly('description');
        $this->validateOnly('groupMinutes');
        $this->validateOnly('koMinutes');
        $this->validateOnly('koWinnerMode');

        $this->tournament->update([
            'name' => $this->tournamentName,
            'description' => $this->description,
            'group_match_duration_minutes' => $this->groupMinutes,
            'ko_match_duration_minutes' => $this->koMinutes,
            'count_throws' => $this->countThrows,
            'play_placement_matches' => $this->playPlacementMatches,
            'ko_sudden_death' => $this->koWinnerMode === 'sudden_death',
        ]);

        Flux::toast(variant: 'success', text: __('Einstellungen gespeichert.'));
    }
}; ?>

<div>
    <div class="mx-auto w-full max-w-5xl space-y-12 p-4 lg:p-6">

        {{-- Title --}}
        <header class="flex flex-col gap-4">
            <div class="font-label flex items-center gap-3 text-trophy-gold">
                <span class="block h-px w-12 bg-trophy-gold"></span>
                <span>{{ __('Administrator') }}</span>
            </div>
            <h1 class="font-display text-stage-text text-[clamp(2rem,5vw,3.5rem)] flex items-center gap-3">
                {{ __('Sonderregeln & Einstellungen') }}
            </h1>
        </header>

        {{-- Settings --}}
        <section class="space-y-5">
            <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                <h2 class="font-display text-2xl text-stage-text">{{ __('Einstellungen') }}</h2>
                <span class="font-label text-stage-text-dim">{{ __('Name & Match-Dauer') }}</span>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <flux:input wire:model="tournamentName" :label="__('Turniername')" />
                <flux:input wire:model="groupMinutes" type="number" min="1" max="60" :label="__('Gruppen-Match (Min)')" />
                <flux:input wire:model="koMinutes" type="number" min="1" max="60" :label="__('KO-Match (Min)')" />
            </div>
            <flux:textarea
                wire:model="description"
                rows="3"
                :label="__('Beschreibung')"
                :description="__('Wird auf der Startseite unter dem Turniernamen angezeigt.')"
            />
        </section>

        {{-- Special rules --}}
        <section class="space-y-5">
            <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                <h2 class="font-display text-2xl text-stage-text">{{ __('Sonderregeln') }}</h2>
                <span class="font-label text-stage-text-dim">{{ __('Schiri-Modus & Erfassung') }}</span>
            </div>

            <div class="rounded-md bg-stage-surface px-5 py-4">
                <flux:switch
                    wire:model="countThrows"
                    :label="__('Würfe zählen')"
                    :description="__('Wenn aus: Schiris zählen nur Strafbecher. Statistiken zu Wurfanzahl & Trefferquote werden ausgeblendet.')"
                />
            </div>

            <div class="rounded-md bg-stage-surface px-5 py-4">
                <flux:switch
                    wire:model="playPlacementMatches"
                    :label="__('Platzierungsspiele austragen')"
                    :description="__('Wenn an: Teams, die die KO-Phase verpassen, spielen vor der KO-Phase ihre Plätze aus — die letzten beiden der Gesamtwertung gegeneinander, die nächsten beiden darüber usw. Wenn aus: Die Platzierungen bleiben wie in der Gruppenphase erspielt.')"
                />
            </div>

            <div class="rounded-md bg-stage-surface px-5 py-4">
                <flux:select
                    wire:model="koWinnerMode"
                    :label="__('Bestimmung des Siegers in der KO-Phase')"
                    :description="__('Wie wird entschieden, welches Team weiterkommt, wenn ein KO-Spiel mit gleicher Becherzahl endet. Entweder entscheidet der Schiri per Sudden
                Death, welches Team weiterkommt oder der Gleichstand wird automatisch aufgelöst (weniger Würfe gewinnen, wenn auch
                gleich gewinnt das Team mit
                weniger
                Strafbechern)')"
                >
                    <flux:select.option value="sudden_death">{{ __('Sudden Death (Auswahl durch Schiri)') }}</flux:select.option>
                    <flux:select.option value="auto">{{ __('Automatisch (weniger Würfe, weniger Strafbecher)') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:button wire:click="saveSettings" variant="primary" data-test="save-special-rules-button">
                {{ __('Speichern') }}
            </flux:button>
        </section>
    </div>
</div>
