<?php

use App\Models\Table;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\GroupGeneratorService;
use App\Services\KoBracketService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Setup')] class extends Component {
    public ?int $tournamentId = null;

    #[Validate('required|string|max:255')]
    public string $tournamentName = '';

    #[Validate('required|integer|min:1|max:60')]
    public int $groupMinutes = 10;

    #[Validate('required|integer|min:1|max:60')]
    public int $koMinutes = 15;

    #[Validate('required|string|max:255')]
    public string $newTableName = '';

    #[Validate('required|string|max:255')]
    public string $newTeamName = '';

    #[Validate('nullable|string|max:9')]
    public ?string $newTeamColor = '#f59e0b';

    public bool $showGroupPreview = false;

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
        $this->groupMinutes = $tournament->group_match_duration_minutes;
        $this->koMinutes = $tournament->ko_match_duration_minutes;
    }

    #[Computed]
    public function tournament(): Tournament
    {
        return Tournament::with(['tables', 'teams'])->findOrFail($this->tournamentId);
    }

    public function saveSettings(): void
    {
        $this->validateOnly('tournamentName');
        $this->validateOnly('groupMinutes');
        $this->validateOnly('koMinutes');

        $this->tournament->update([
            'name' => $this->tournamentName,
            'group_match_duration_minutes' => $this->groupMinutes,
            'ko_match_duration_minutes' => $this->koMinutes,
        ]);

        Flux::toast(variant: 'success', text: __('Einstellungen gespeichert.'));
    }

    public function addTable(): void
    {
        $this->validateOnly('newTableName');

        abort_unless($this->tournament->isSetup(), 422);

        Table::create([
            'tournament_id' => $this->tournamentId,
            'name' => $this->newTableName,
        ]);

        $this->newTableName = '';
        unset($this->tournament);
    }

    public function removeTable(int $tableId): void
    {
        abort_unless($this->tournament->isSetup(), 422);

        Table::where('tournament_id', $this->tournamentId)->where('id', $tableId)->delete();
        unset($this->tournament);
    }

    public function addTeam(): void
    {
        $this->validateOnly('newTeamName');

        abort_unless($this->tournament->isSetup(), 422);

        Team::create([
            'tournament_id' => $this->tournamentId,
            'name' => $this->newTeamName,
            'color' => $this->newTeamColor,
        ]);

        $this->newTeamName = '';
        $this->newTeamColor = '#f59e0b';
        unset($this->tournament);
    }

    public function removeTeam(int $teamId): void
    {
        abort_unless($this->tournament->isSetup(), 422);

        Team::where('tournament_id', $this->tournamentId)->where('id', $teamId)->delete();
        unset($this->tournament);
    }

    public function showPreview(): void
    {
        $this->showGroupPreview = true;
    }

    public function cancelPreview(): void
    {
        $this->showGroupPreview = false;
    }

    public function confirmGenerate(GroupGeneratorService $service): void
    {
        $service->generate($this->tournament);
        $this->showGroupPreview = false;
        unset($this->tournament);

        Flux::toast(variant: 'success', text: __('Gruppenphase generiert.'));

        $this->redirectRoute('matches.index', navigate: true);
    }

    public function startKoPhase(KoBracketService $service): void
    {
        $service->startKoPhase($this->tournament);
        unset($this->tournament);

        Flux::toast(variant: 'success', text: __('KO-Phase gestartet.'));

        $this->redirectRoute('matches.index', navigate: true);
    }

    public function resetTournament(): void
    {
        $t = $this->tournament;

        \App\Models\GameMatch::where('tournament_id', $t->id)->delete();
        foreach ($t->groups as $g) {
            $g->teams()->detach();
        }
        $t->groups()->delete();
        $t->update(['status' => 'setup']);
        unset($this->tournament);

        Flux::toast(variant: 'success', text: __('Turnier zurückgesetzt.'));
    }

    #[Computed]
    public function groupPreview(): array
    {
        return app(GroupGeneratorService::class)->preview($this->tournament);
    }

    #[Computed]
    public function koPhaseReady(): bool
    {
        return $this->tournament->isGroupPhase()
            && app(KoBracketService::class)->isGroupPhaseComplete($this->tournament);
    }
}; ?>

<div>
    @php
        $t = $this->tournament;
        $phaseLabels = [
            'setup' => 'Vorbereitung',
            'group' => 'Gruppenphase',
            'ko' => 'KO-Phase',
            'finished' => 'Beendet',
        ];
    @endphp

    <div class="mx-auto w-full max-w-5xl space-y-12 p-4 lg:p-6">

        {{-- Title --}}
        <header class="flex flex-col gap-4">
            <div class="font-label flex items-center gap-3 text-trophy-gold">
                <span class="block h-px w-12 bg-trophy-gold"></span>
                <span>{{ $phaseLabels[$t->status] ?? $t->status }}</span>
            </div>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h1 class="font-display text-stage-text text-[clamp(2rem,5vw,3.5rem)]">Turnier-Setup</h1>
                @if (! $t->isSetup())
                    <flux:modal.trigger name="reset-confirm">
                        <flux:button variant="ghost" size="sm">Turnier zurücksetzen</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
        </header>

        {{-- Settings --}}
        <section class="space-y-5">
            <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                <h2 class="font-display text-2xl text-stage-text">Einstellungen</h2>
                <span class="font-label text-stage-text-dim">Name & Match-Dauer</span>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <flux:input wire:model="tournamentName" label="Turniername" />
                <flux:input wire:model="groupMinutes" type="number" min="1" max="60" label="Gruppen-Match (Min)" />
                <flux:input wire:model="koMinutes" type="number" min="1" max="60" label="KO-Match (Min)" />
            </div>
            <flux:button wire:click="saveSettings" variant="primary">Speichern</flux:button>
        </section>

        {{-- Tables --}}
        <section class="space-y-5">
            <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                <h2 class="font-display text-2xl text-stage-text">Tische</h2>
                <span class="font-numeric text-2xl font-bold text-stage-text">{{ $t->tables->count() }}</span>
            </div>

            @if ($t->isSetup())
                <form wire:submit="addTable" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[240px] flex-1">
                        <flux:input wire:model="newTableName" label="Tisch-Name" placeholder="z.B. Tisch Kellerbar" />
                    </div>
                    <flux:button type="submit" variant="primary">Hinzufügen</flux:button>
                </form>
            @endif

            @if ($t->tables->isEmpty())
                <p class="text-sm text-stage-text-dim">Noch keine Tische angelegt.</p>
            @else
                <ul class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($t->tables as $table)
                        <li class="flex items-center justify-between rounded-md bg-stage-surface px-4 py-3">
                            <span class="font-medium text-stage-text">{{ $table->name }}</span>
                            @if ($t->isSetup())
                                <button wire:click="removeTable({{ $table->id }})" wire:confirm="Tisch wirklich löschen?"
                                        class="text-xs font-semibold text-status-danger hover:underline">Entfernen</button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Teams --}}
        <section class="space-y-5">
            <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                <h2 class="font-display text-2xl text-stage-text">Teams</h2>
                <span class="font-numeric text-2xl font-bold text-stage-text">{{ $t->teams->count() }}</span>
            </div>

            @if ($t->isSetup())
                <form wire:submit="addTeam" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[240px] flex-1">
                        <flux:input wire:model="newTeamName" label="Team-Name" placeholder="z.B. Team Shotgun" />
                    </div>
                    <div class="w-32">
                        <flux:input wire:model="newTeamColor" type="color" label="Farbe" />
                    </div>
                    <flux:button type="submit" variant="primary">Hinzufügen</flux:button>
                </form>
            @endif

            @if ($t->teams->isEmpty())
                <p class="text-sm text-stage-text-dim">Noch keine Teams angelegt.</p>
            @else
                <ul class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($t->teams as $team)
                        <li class="flex items-center justify-between rounded-md bg-stage-surface px-4 py-3">
                            <span class="team-tag">
                                <span class="team-dot" @if($team->color) style="background-color: {{ $team->color }}" @endif></span>
                                <span class="font-medium text-stage-text">{{ $team->name }}</span>
                            </span>
                            @if ($t->isSetup())
                                <button wire:click="removeTeam({{ $team->id }})" wire:confirm="Team wirklich löschen?"
                                        class="text-xs font-semibold text-status-danger hover:underline">Entfernen</button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Generate groups: the moment-of-go --}}
        @if ($t->isSetup())
            <section class="overflow-hidden rounded-lg border border-trophy-gold/30 bg-trophy-gold-soft">
                <div class="px-6 py-8 lg:px-10 lg:py-10">
                    <div class="font-label flex items-center gap-3 text-trophy-gold">
                        <span class="block h-px w-12 bg-trophy-gold"></span>
                        <span>Bereit zum Auslosen</span>
                    </div>
                    <h2 class="mt-3 font-display text-stage-text text-2xl lg:text-3xl">Gruppenphase generieren</h2>
                    <p class="mt-3 max-w-lg text-sm text-stage-text-muted">
                        <span class="font-numeric text-stage-text">{{ $t->teams->count() }}</span> Teams werden auf
                        <span class="font-numeric text-stage-text">{{ $t->tables->count() }}</span> Tische verteilt.
                        Jedes Team spielt in seiner Gruppe gegen jedes andere einmal.
                    </p>
                    <button wire:click="showPreview"
                            @disabled($t->teams->count() < 2 || $t->tables->isEmpty())
                            class="mt-6 inline-flex items-center gap-2 rounded-md bg-trophy-gold px-6 py-3 text-base font-bold text-stage-bg hover:bg-trophy-gold-deep disabled:opacity-40 disabled:cursor-not-allowed transition">
                        Gruppen generieren →
                    </button>
                </div>
            </section>
        @endif

        {{-- Start KO --}}
        @if ($t->isGroupPhase() && $this->koPhaseReady)
            <section class="overflow-hidden rounded-lg border border-trophy-gold/30 bg-trophy-gold-soft">
                <div class="px-6 py-8 lg:px-10 lg:py-10">
                    <div class="font-label flex items-center gap-3 text-trophy-gold">
                        <span class="block h-px w-12 bg-trophy-gold"></span>
                        <span>Gruppenphase abgeschlossen</span>
                    </div>
                    <h2 class="mt-3 font-display text-stage-text text-2xl lg:text-3xl">KO-Phase starten</h2>
                    <p class="mt-3 max-w-lg text-sm text-stage-text-muted">
                        Alle Matches der Gruppenphase sind beendet. Aus den besten Teams jeder Gruppe wird das KO-Bracket gebaut.
                    </p>
                    <button wire:click="startKoPhase"
                            class="mt-6 inline-flex items-center gap-2 rounded-md bg-trophy-gold px-6 py-3 text-base font-bold text-stage-bg hover:bg-trophy-gold-deep transition">
                        KO-Phase starten →
                    </button>
                </div>
            </section>
        @endif
    </div>

    <flux:modal name="group-preview" wire:model="showGroupPreview" class="max-w-2xl">
        <div class="space-y-5">
            <flux:heading size="lg">Vorschau Gruppenverteilung</flux:heading>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @foreach ($this->groupPreview as $bucket)
                    <div class="rounded-md bg-stage-surface-2 p-4">
                        <div class="font-label text-stage-text-dim">{{ $bucket['table'] }}</div>
                        <ul class="mt-2 space-y-1 text-sm text-stage-text">
                            @foreach ($bucket['teams'] as $team)
                                <li>· {{ $team }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="cancelPreview" variant="ghost">Abbrechen</flux:button>
                <button wire:click="confirmGenerate"
                        class="inline-flex items-center gap-2 rounded-md bg-trophy-gold px-5 py-2.5 text-sm font-bold text-stage-bg hover:bg-trophy-gold-deep transition">
                    Bestätigen
                </button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="reset-confirm" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">Turnier zurücksetzen?</flux:heading>
            <p class="text-sm text-stage-text-muted">Alle Matches, Gruppen und Ergebnisse werden gelöscht. Tische und Teams bleiben erhalten.</p>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button wire:click="resetTournament" variant="danger">Zurücksetzen</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
