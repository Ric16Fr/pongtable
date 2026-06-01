<?php

use App\Models\Tournament;
use App\Services\StatisticsService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Statistik')] class extends Component {
    public ?int $tournamentId = null;

    public function mount(?int $tournamentId = null): void
    {
        $this->tournamentId = $tournamentId ?? Tournament::query()->latest()->value('id');
    }

    #[Computed]
    public function tournament(): ?Tournament
    {
        return $this->tournamentId ? Tournament::find($this->tournamentId) : null;
    }

    #[Computed]
    public function stats(): array
    {
        $t = $this->tournament;

        return $t ? app(StatisticsService::class)->summary($t) : [];
    }
}; ?>

@php
$stats = $this->stats;
$tiles = [];
if ($stats) {
    if ($stats['sharpest_shooter'] ?? null) {
        $tiles[] = ['key' => 'shooter', 'label' => 'Schärfste Schützen', 'info' => 'Höchste Trefferquote über alle Matches — getroffene Becher pro Wurf.', 'value' => $stats['sharpest_shooter']['team'], 'sub' => $stats['sharpest_shooter']['rate'].'% Quote · '.$stats['sharpest_shooter']['scored'].'/'.$stats['sharpest_shooter']['throws']];
    }
    if ($stats['water_spitter'] ?? null) {
        $tiles[] = ['key' => 'spitter', 'label' => 'Wasserspeier', 'info' => 'Niedrigste Trefferquote — viele Würfe, wenig Becher.', 'value' => $stats['water_spitter']['team'], 'sub' => $stats['water_spitter']['rate'].'% Trefferquote'];
    }
    if ($stats['blitz_win'] ?? null) {
        $d = $stats['blitz_win']['duration'];
        $tiles[] = ['key' => 'blitz', 'label' => 'Blitzsieg', 'info' => 'Schnellster Sieg in der Gruppenphase, gemessen an der Spieldauer.', 'value' => $stats['blitz_win']['team'], 'sub' => sprintf('%02d:%02d gegen %s', intdiv($d, 60), $d % 60, $stats['blitz_win']['opponent'])];
    }
    if ($stats['marathon'] ?? null) {
        $d = $stats['marathon']['duration'];
        $tiles[] = ['key' => 'marathon', 'label' => 'Marathonspieler', 'info' => 'Längstes Match nach Dauer — die zwei Teams haben sich am längsten duelliert.', 'value' => implode(' vs ', $stats['marathon']['teams']), 'sub' => sprintf('%02d:%02d', intdiv($d, 60), $d % 60)];
    }
    if ($stats['cup_emperor'] ?? null) {
        $tiles[] = ['key' => 'emperor', 'label' => 'Becherkaiser', 'info' => 'Meiste getroffene Becher eines Teams in einem einzelnen Match.', 'value' => $stats['cup_emperor']['team'], 'sub' => $stats['cup_emperor']['cups'].' Becher gegen '.$stats['cup_emperor']['opponent']];
    }
    if ($stats['penalty_magnet'] ?? null) {
        $tiles[] = ['key' => 'penalty', 'label' => 'Strafbechermagnet', 'info' => 'Team mit den meisten Strafbechern über das gesamte Turnier.', 'value' => $stats['penalty_magnet']['team'], 'sub' => $stats['penalty_magnet']['penalty_cups'].' Strafbecher'];
    }
    if ($stats['efficiency'] ?? null) {
        $tiles[] = ['key' => 'efficiency', 'label' => 'Effizienzrate', 'info' => 'Beste Wurf-Bilanz: (Treffer − Strafbecher) pro Wurf, als Prozentwert.', 'value' => $stats['efficiency']['team'], 'sub' => $stats['efficiency']['rate'].'%'];
    }
    if ($stats['most_played'] ?? null) {
        $tiles[] = ['key' => 'played', 'label' => 'Heiß gespielt', 'info' => 'Team mit den meisten gespielten Matches im Turnier.', 'value' => $stats['most_played']['team'], 'sub' => $stats['most_played']['matches'].' Matches'];
    }
}
@endphp

<div class="mx-auto w-full max-w-6xl space-y-10 p-4 lg:p-6" wire:poll.30s>

    {{-- Title --}}
    <header class="flex flex-col gap-4">
        @if ($this->tournament)
            <div class="font-label flex items-center gap-3 text-trophy-gold">
                <span class="block h-px w-12 bg-trophy-gold"></span>
                <span>Turnier-Statistik</span>
            </div>
            <h1 class="font-display text-stage-text text-[clamp(2rem,5vw,3.5rem)]">{{ $this->tournament->name }}</h1>
        @else
            <h1 class="font-display text-stage-text text-3xl">Kein Turnier</h1>
        @endif
    </header>

    @if (empty($tiles) && ! ($stats['champion'] ?? null))
        <p class="text-stage-text-muted">Noch keine Daten. Statistiken erscheinen sobald Matches gespielt wurden.</p>
    @else
        {{-- Champion banner: full bleed, gold-drenched --}}
        @if ($stats['champion'] ?? null)
            <section class="overflow-hidden rounded-lg border border-trophy-gold/40 bg-trophy-gold-soft px-6 py-10 lg:px-12 lg:py-14">
                <div class="font-label flex items-center gap-3 text-trophy-gold">
                    <span class="block h-px w-12 bg-trophy-gold"></span>
                    <span>Turniersieger</span>
                </div>
                <h2 class="mt-4 font-display text-trophy-gold text-[clamp(2.5rem,8vw,5.5rem)]">
                    {{ $stats['champion']['name'] }}
                </h2>
                <p class="mt-4 font-numeric text-stage-text-muted">
                    Cups gesamt: <span class="text-stage-text font-bold">{{ $stats['total_cups'] ?? 0 }}</span>
                </p>
            </section>
        @endif

        {{-- Fun stats asymmetric grid --}}
        @if (! empty($tiles))
            <section class="space-y-5">
                <h2 class="font-label text-stage-text-muted">Fun Stats</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                    @foreach ($tiles as $i => $tile)
                        @php
                            $span = match (true) {
                                $i === 0 => 'sm:col-span-4',
                                $i === 1 => 'sm:col-span-2',
                                $i % 5 === 2 => 'sm:col-span-3',
                                $i % 5 === 3 => 'sm:col-span-3',
                                $i % 5 === 4 => 'sm:col-span-2',
                                default => 'sm:col-span-4',
                            };
                        @endphp
                        <div class="{{ $span }} flex flex-col justify-between gap-3 rounded-lg bg-stage-surface px-5 py-5 lg:px-6 lg:py-6">
                            <div class="flex items-start justify-between gap-3">
                                <span class="font-label text-stage-text-dim">{{ $tile['label'] }}</span>
                                @if (! empty($tile['info']))
                                    <flux:tooltip :content="$tile['info']" toggleable>
                                        <button type="button" aria-label="Was bedeutet das?"
                                                class="-mr-1 -mt-1 shrink-0 rounded-full p-1 text-stage-text-dim hover:text-stage-text-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-stage-line-strong transition">
                                            <flux:icon.information-circle class="size-4" />
                                        </button>
                                    </flux:tooltip>
                                @endif
                            </div>
                            <div class="flex flex-col gap-1">
                                <span class="font-display text-stage-text text-2xl leading-tight lg:text-3xl">{{ $tile['value'] }}</span>
                                @if ($tile['sub'])
                                    <span class="font-numeric text-sm text-stage-text-muted">{{ $tile['sub'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="flex items-baseline justify-between border-t border-stage-line pt-5">
            <span class="font-label text-stage-text-muted">Cups gesamt über das Turnier</span>
            <span class="font-numeric text-3xl font-semibold text-stage-text lg:text-4xl">{{ $stats['total_cups'] ?? 0 }}</span>
        </div>
    @endif
</div>
