<?php

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Sonderregeln & Einstellungen')]
class extends Component {
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

    public bool $determineCupKing = false;

    public bool $hideCertificateCircles = false;

    public bool $showSchedule = false;

    #[Validate('nullable|string|max:5000')]
    public string $schedule = '';

    /**
     * Member names keyed by team id, two slots per team for the naming modal.
     *
     * @var array<int, array<int, string>>
     */
    public array $memberNames = [];

    public function mount(): void
    {
        $tournament = Tournament::query()->latest()->first();

        if (!$tournament) {
            $tournament = Tournament::create([
                'name' => 'Bierpong WM ' . now()->year,
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
        $this->determineCupKing = $tournament->determine_cup_king ?? false;
        $this->hideCertificateCircles = $tournament->hide_certificate_circles ?? false;
        $this->showSchedule = $tournament->show_schedule ?? false;
        $this->schedule = $tournament->schedule ?? '';
    }

    #[Computed]
    public function tournament(): Tournament
    {
        return Tournament::findOrFail($this->tournamentId);
    }

    /**
     * Teams of the current tournament, used by the member-naming modal.
     *
     * @return Collection<int, Team>
     */
    #[Computed]
    public function teams(): Collection
    {
        return $this->tournament->teams()->with('members')->orderBy('name')->get();
    }

    public function saveSettings(): void
    {
        $this->validateOnly('tournamentName');
        $this->validateOnly('description');
        $this->validateOnly('groupMinutes');
        $this->validateOnly('koMinutes');
        $this->validateOnly('koWinnerMode');
        $this->validateOnly('schedule');

        $this->tournament->update([
            'name' => $this->tournamentName,
            'description' => $this->description,
            'group_match_duration_minutes' => $this->groupMinutes,
            'ko_match_duration_minutes' => $this->koMinutes,
            'count_throws' => $this->countThrows,
            'play_placement_matches' => $this->playPlacementMatches,
            'ko_sudden_death' => $this->koWinnerMode === 'sudden_death',
            'determine_cup_king' => $this->determineCupKing,
            'hide_certificate_circles' => $this->hideCertificateCircles,
            'show_schedule' => $this->showSchedule,
            'schedule' => $this->schedule,
        ]);

        Flux::toast(variant: 'success', text: __('Einstellungen gespeichert.'));
    }

    /**
     * Pre-fill the member-naming modal from existing members and open it.
     */
    public function openMembersModal(): void
    {
        $this->memberNames = [];

        foreach ($this->teams as $team) {
            $names = $team->members->pluck('name')->values()->all();
            $this->memberNames[$team->id] = [
                $names[0] ?? '',
                $names[1] ?? '',
            ];
        }

        Flux::modal('name-members')->show();
    }

    /**
     * Replace each team's members with the non-empty names entered in the modal.
     */
    public function saveMembers(): void
    {
        DB::transaction(function () {
            foreach ($this->teams as $team) {
                $names = collect($this->memberNames[$team->id] ?? [])
                    ->map(fn($name) => trim((string)$name))
                    ->filter()
                    ->values();

                $team->members()->delete();

                foreach ($names as $name) {
                    TeamMember::create([
                        'team_id' => $team->id,
                        'name' => $name,
                    ]);
                }
            }
        });

        unset($this->teams);
        Flux::modal('name-members')->close();
        Flux::toast(variant: 'success', text: __('Teammitglieder gespeichert.'));
    }
}; ?>

<div >
    <div class="mx-auto w-full max-w-5xl space-y-12 p-4 lg:p-6" >

        {{-- Title --}}
        <header class="flex flex-col gap-4" >
            <div class="font-label flex items-center gap-3 text-trophy-gold" >
                <span class="block h-px w-12 bg-trophy-gold" ></span >
                <span >{{ __('Administrator') }}</span >
            </div >
            <h1 class="font-display text-stage-text text-[clamp(2rem,5vw,3.5rem)] flex items-center gap-3" >
                {{ __('Sonderregeln & Einstellungen') }}
            </h1 >
        </header >

        {{-- Settings --}}
        <section class="space-y-5" >
            <div class="flex items-baseline justify-between border-b border-stage-line pb-3" >
                <h2 class="font-display text-2xl text-stage-text" >{{ __('Einstellungen') }}</h2 >
                <span class="font-label text-stage-text-dim" >{{ __('Name & Match-Dauer') }}</span >
            </div >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3" >
                <flux:input wire:model="tournamentName" :label="__('Turniername')" />
                <flux:input wire:model="groupMinutes" type="number" min="1" max="60" :label="__('Gruppen-Match (Min)')" />
                <flux:input wire:model="koMinutes" type="number" min="1" max="60" :label="__('KO-Match (Min)')" />
            </div >
            <flux:textarea
                wire:model="description"
                rows="3"
                :label="__('Beschreibung')"
                :description="__('Wird auf der Startseite unter dem Turniernamen angezeigt.')"
            />
        </section >

        {{-- Special rules --}}
        <section class="space-y-5" >
            <div class="flex items-baseline justify-between border-b border-stage-line pb-3" >
                <h2 class="font-display text-2xl text-stage-text" >{{ __('Sonderregeln') }}</h2 >
                <span class="font-label text-stage-text-dim" >{{ __('Schiri-Modus & Erfassung') }}</span >
            </div >

            <div class="rounded-md bg-stage-surface px-5 py-4" >
                <flux:switch
                    wire:model="countThrows"
                    :label="__('Würfe zählen')"
                    :description="__('Wenn aus: Schiris zählen nur Strafbecher. Statistiken zu Wurfanzahl & Trefferquote werden ausgeblendet.')"
                />
            </div >

            <div class="rounded-md bg-stage-surface px-5 py-4" >
                <flux:switch
                    wire:model="playPlacementMatches"
                    :label="__('Platzierungsspiele austragen')"
                    :description="__('Wenn an: Teams, die die KO-Phase verpassen, spielen vor der KO-Phase ihre Plätze aus — die letzten beiden der Gesamtwertung gegeneinander, die nächsten beiden darüber usw. Wenn aus: Die Platzierungen bleiben wie in der Gruppenphase erspielt.')"
                />
            </div >

            <div class="rounded-md bg-stage-surface px-5 py-4" >
                <flux:select
                    wire:model="koWinnerMode"
                    :label="__('Bestimmung des Siegers in der KO-Phase')"
                    :description="__('Wie wird entschieden, welches Team weiterkommt, wenn ein KO-Spiel mit gleicher Becherzahl endet. Entweder entscheidet der Schiri per Sudden
                Death, welches Team weiterkommt oder der Gleichstand wird automatisch aufgelöst (weniger Würfe gewinnen, wenn auch
                gleich gewinnt das Team mit
                weniger
                Strafbechern)')"
                >
                    <flux:select.option value="sudden_death" >{{ __('Sudden Death (Auswahl durch Schiri)') }}</flux:select.option >
                    <flux:select.option value="auto" >{{ __('Automatisch (weniger Würfe, weniger Strafbecher)') }}</flux:select.option >
                </flux:select >
            </div >

            <div class="rounded-md bg-stage-surface px-5 py-4" >
                <flux:switch
                    wire:model.live="determineCupKing"
                    :label="__('Wurfkönig ermitteln')"
                    :description="__('Wenn an: Pro Team werden die Teammitglieder benannt. Nach jedem Spiel werden die getroffenen Becher auf die Spieler verteilt. Die Statistik kürt den Spieler mit den meisten getroffenen Bechern als Wurfkönig.')"
                />

                @if ($this->determineCupKing)
                    <div class="mt-4 border-t border-stage-line pt-4" >
                        @if ($this->tournament->isSetup())
                            <p class="text-sm text-stage-text-dim" data-test="cup-king-setup-hint" >
                                {{ __('Die Gruppen müssen erst angelegt und zugelost (oder importiert) werden, bevor die Teammitglieder benannt werden können.') }}
                            </p >
                        @else
                            <flux:button wire:click="openMembersModal" variant="filled" icon="users" data-test="name-members-button" >
                                {{ __('Teammitglieder benennen') }}
                            </flux:button >
                        @endif
                    </div >
                @endif
            </div >
        </section >

        {{-- UI settings (rarely needed, collapsed by default) --}}
        <section >
            <details class="group space-y-5" data-test="ui-settings" >
                <summary
                    class="flex cursor-pointer items-baseline justify-between border-b border-stage-line pb-3 [&::-webkit-details-marker]:hidden" >
                    <h2 class="font-display text-2xl text-stage-text" >{{ __('UI-Einstellungen') }}</h2 >
                    <span class="font-label flex items-center gap-2 text-stage-text-dim" >
                        {{ __('Selten benötigt') }}
                        <flux:icon.chevron-down class="size-4 transition-transform group-open:rotate-180" />
                    </span >
                </summary >

                <div class="space-y-5 pt-5" >
                    <p class="text-sm text-stage-text-dim" >
                        {{ __('Diese Sektion enthält selten genutzte UI Einstellungen') }}
                    </p >

                    <div class="rounded-md bg-stage-surface px-5 py-4" >
                        <flux:switch
                            wire:model="hideCertificateCircles"
                            :label="__('Farbige Kreise in den Urkunden ausblenden')"
                            :description="__('Blendet die dekorativen roten und blauen Kreise aus, die auf Urkunden dargestellt werden. Sinnvoll, wenn auf marmoriertem oder farbigem Papier gedruckt werden soll.')"
                        />
                    </div >

                    <div class="rounded-md bg-stage-surface px-5 py-4" >
                        <flux:switch
                            wire:model.live="showSchedule"
                            :label="__('Turnierplan anzeigen')"
                            :description="__('Zeigt öffentlich einen Tab „Turnierplan“ neben „Regeln“ mit der geplanten Spielreihenfolge. Rein informativ — die Match-Liste und der Spielablauf werden dadurch nicht verändert; Schiris können weiterhin jedes Spiel jederzeit starten.')"
                        />

                        @if ($showSchedule)
                            <div class="mt-4 border-t border-stage-line pt-4" >
                                <flux:textarea
                                    wire:model="schedule"
                                    rows="8"
                                    :label="__('Spielreihenfolge')"
                                    :description="__('Eine Zeile pro Spiel, Teams mit Semikolon getrennt — z. B. „Team 1;Team 2“. Der Tab zeigt eine Zeile je Eintrag. Der Tab erscheint erst, sobald hier etwas eingetragen und gespeichert wurde.')"
                                    placeholder="Team 1;Team 2&#10;Team 3;Team 4"
                                    data-test="schedule-input"
                                />
                            </div >
                        @endif
                    </div >

                </div >
            </details >
        </section >

        {{-- Single save button for all settings above --}}
        <flux:button wire:click="saveSettings" variant="primary" data-test="save-special-rules-button" >
            {{ __('Speichern') }}
        </flux:button >
    </div >

    {{-- Member naming modal --}}
    @if ($this->determineCupKing && ! $this->tournament->isSetup())
        <flux:modal name="name-members" class="max-w-2xl" >
            <div class="space-y-6" >
                <div >
                    <flux:heading size="lg" >{{ __('Teammitglieder benennen') }}</flux:heading >
                    <flux:text class="mt-2" >{{ __('Trage für jedes Team die beiden Spieler ein.') }}</flux:text >
                </div >

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2" >
                    @foreach ($this->teams as $team)
                        <div class="space-y-3 rounded-md bg-stage-surface px-4 py-3" >
                            <div class="flex items-center gap-2" >
                                <span class="font-label text-stage-text" >{{ $team->name }}</span >
                            </div >
                            <flux:input wire:model="memberNames.{{ $team->id }}.0" :label="__('Spieler 1')" :placeholder="__('Name')" />
                            <flux:input wire:model="memberNames.{{ $team->id }}.1" :label="__('Spieler 2')" :placeholder="__('Name')" />
                        </div >
                    @endforeach
                </div >

                <div class="flex gap-2" >
                    <flux:spacer />
                    <flux:modal.close >
                        <flux:button variant="ghost" >{{ __('Abbrechen') }}</flux:button >
                    </flux:modal.close >
                    <flux:button wire:click="saveMembers" variant="primary" data-test="save-members-button" >
                        {{ __('Speichern') }}
                    </flux:button >
                </div >
            </div >
        </flux:modal >
    @endif
</div >
