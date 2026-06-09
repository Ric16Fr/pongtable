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
    public string $newTableName = '';

    #[Validate('required|string|max:255')]
    public string $newTeamName = '';

    #[Validate('nullable|string|max:9')]
    public ?string $newTeamColor = '#f59e0b';

    #[Validate('required|string|min:5')]
    public string $csvContent = '';

    public bool $addingTable = false;

    public bool $addingTeam = false;

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
    }

    #[Computed]
    public function tournament(): Tournament
    {
        return Tournament::with(['tables', 'teams'])->findOrFail($this->tournamentId);
    }

    public function startAddingTable(): void
    {
        $this->addingTable = true;
        $this->newTableName = '';
        $this->resetErrorBag('newTableName');
    }

    public function cancelAddingTable(): void
    {
        $this->addingTable = false;
        $this->newTableName = '';
        $this->resetErrorBag('newTableName');
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

    public function startAddingTeam(): void
    {
        $this->addingTeam = true;
        $this->newTeamName = '';
        $this->newTeamColor = '#f59e0b';
        $this->resetErrorBag('newTeamName');
    }

    public function cancelAddingTeam(): void
    {
        $this->addingTeam = false;
        $this->newTeamName = '';
        $this->newTeamColor = '#f59e0b';
        $this->resetErrorBag('newTeamName');
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

    public function uploadGroups(GroupGeneratorService $service): void
    {
        $this->validateOnly('csvContent');

        $service->importFromCsv($this->tournament, $this->csvContent);

        $this->csvContent = '';
        unset($this->tournament);
        Flux::modal('upload-groups')->close();

        Flux::toast(variant: 'success', text: __('Gruppen aus CSV übernommen.'));

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
                <div class="flex flex-wrap items-center gap-2">
                    @if ($t->isSetup())
                        <flux:modal.trigger name="upload-groups">
                            <flux:button variant="ghost" size="sm" icon="arrow-up-tray" data-test="open-upload-groups">
                                Gruppen hochladen
                            </flux:button>
                        </flux:modal.trigger>
                    @else
                        <flux:modal.trigger name="reset-confirm">
                            <flux:button variant="ghost" size="sm">Turnier zurücksetzen</flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>
            </div>
        </header>

        {{-- Tables --}}
        <section class="space-y-5">
            <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                <h2 class="font-display text-2xl text-stage-text">Tische</h2>
                <span class="font-numeric text-2xl font-bold text-stage-text">{{ $t->tables->count() }}</span>
            </div>

            @if ($t->tables->isEmpty() && ! $addingTable)
                <p class="text-sm text-stage-text-dim">Noch keine Tische angelegt.</p>
            @else
                <ul class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($t->tables as $table)
                        <li class="group flex items-center justify-between rounded-md bg-stage-surface px-4 py-3">
                            <span class="font-medium text-stage-text">{{ $table->name }}</span>
                            @if ($t->isSetup())
                                <button wire:click="removeTable({{ $table->id }})" wire:confirm="Tisch wirklich löschen?"
                                        type="button"
                                        aria-label="Tisch entfernen"
                                        class="rounded-md p-1 text-stage-text-muted opacity-0 transition group-hover:opacity-100 hover:bg-stage-surface-2 hover:text-status-danger focus:opacity-100 focus:outline-none">
                                    <flux:icon.x-mark class="size-4" />
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($t->isSetup())
                @if ($addingTable)
                    <form wire:submit="addTable"
                          x-data
                          x-init="$nextTick(() => $refs.tableInput.focus())"
                          class="flex flex-wrap items-end gap-3 rounded-md border border-dashed border-stage-line-strong bg-stage-surface px-4 py-3">
                        <div class="min-w-[240px] flex-1">
                            <flux:input wire:model="newTableName"
                                        placeholder="z.B. Tisch Kellerbar"
                                        x-ref="tableInput"
                                        x-on:keydown.escape.prevent="$wire.cancelAddingTable()" />
                        </div>
                        <flux:button type="submit" variant="primary">Speichern</flux:button>
                        <button type="button" wire:click="cancelAddingTable"
                                aria-label="Abbrechen"
                                class="rounded-md p-2 text-stage-text-muted hover:bg-stage-surface-2 hover:text-stage-text focus:outline-none">
                            <flux:icon.x-mark class="size-5" />
                        </button>
                    </form>
                @else
                    <button wire:click="startAddingTable" type="button"
                            class="inline-flex items-center gap-2 rounded-md border border-dashed border-stage-line-strong px-4 py-2 text-sm font-medium text-stage-text-muted hover:border-stage-text hover:text-stage-text transition">
                        <flux:icon.plus class="size-4" />
                        Tisch hinzufügen
                    </button>
                @endif
            @endif
        </section>

        {{-- Teams --}}
        <section class="space-y-5">
            <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                <h2 class="font-display text-2xl text-stage-text">Teams</h2>
                <span class="font-numeric text-2xl font-bold text-stage-text">{{ $t->teams->count() }}</span>
            </div>

            @if ($t->teams->isEmpty() && ! $addingTeam)
                <p class="text-sm text-stage-text-dim">Noch keine Teams angelegt.</p>
            @else
                <ul class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    @foreach ($t->teams as $team)
                        <li class="group flex items-center justify-between rounded-md bg-stage-surface px-4 py-3">
                            <span class="team-tag">
                                <span class="team-dot" @if($team->color) style="background-color: {{ $team->color }}" @endif></span>
                                <span class="font-medium text-stage-text">{{ $team->name }}</span>
                            </span>
                            @if ($t->isSetup())
                                <button wire:click="removeTeam({{ $team->id }})" wire:confirm="Team wirklich löschen?"
                                        type="button"
                                        aria-label="Team entfernen"
                                        class="rounded-md p-1 text-stage-text-muted opacity-0 transition group-hover:opacity-100 hover:bg-stage-surface-2 hover:text-status-danger focus:opacity-100 focus:outline-none">
                                    <flux:icon.x-mark class="size-4" />
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($t->isSetup())
                @if ($addingTeam)
                    <form wire:submit="addTeam"
                          x-data
                          x-init="$nextTick(() => $refs.teamInput.focus())"
                          class="flex flex-wrap items-end gap-3 rounded-md border border-dashed border-stage-line-strong bg-stage-surface px-4 py-3">
                        <div class="min-w-[240px] flex-1">
                            <flux:input wire:model="newTeamName"
                                        placeholder="z.B. Team Shotgun"
                                        x-ref="teamInput"
                                        x-on:keydown.escape.prevent="$wire.cancelAddingTeam()" />
                        </div>
                        <div class="w-24">
                            <flux:input wire:model="newTeamColor" type="color" />
                        </div>
                        <flux:button type="submit" variant="primary">Speichern</flux:button>
                        <button type="button" wire:click="cancelAddingTeam"
                                aria-label="Abbrechen"
                                class="rounded-md p-2 text-stage-text-muted hover:bg-stage-surface-2 hover:text-stage-text focus:outline-none">
                            <flux:icon.x-mark class="size-5" />
                        </button>
                    </form>
                @else
                    <button wire:click="startAddingTeam" type="button"
                            class="inline-flex items-center gap-2 rounded-md border border-dashed border-stage-line-strong px-4 py-2 text-sm font-medium text-stage-text-muted hover:border-stage-text hover:text-stage-text transition">
                        <flux:icon.plus class="size-4" />
                        Team hinzufügen
                    </button>
                @endif
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

    <flux:modal name="upload-groups" class="max-w-2xl">
        <form wire:submit="uploadGroups" class="space-y-5">
            <div>
                <flux:heading size="lg">Gruppen hochladen</flux:heading>
                <flux:text class="mt-2">
                    Füge eine semikolon-getrennte CSV ein. Erste Zeile enthält die Gruppennamen
                    (werden ignoriert — wir benennen die Gruppen alphabetisch A, B, C, …),
                    danach folgen die Teams. Die Zellen werden reihum auf die Gruppen verteilt.
                </flux:text>
                <flux:text class="mt-2 text-status-danger">
                    Vorhandene Teams werden dabei ersetzt.
                </flux:text>
            </div>

            <flux:textarea
                wire:model="csvContent"
                rows="10"
                placeholder="Gruppe A;Gruppe B;Gruppe C;Gruppe D&#10;Team 1;Team 2;Team 3;Team 4&#10;..."
                data-test="csv-content"
            />
            @error('csvContent') <p class="text-sm text-status-danger">{{ $message }}</p> @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="upload-groups-button">
                    Übernehmen
                </flux:button>
            </div>
        </form>
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
