<?php

use App\Models\Tournament;
use Livewire\Component;

new class extends Component {
    public Tournament $tournament;

    public function mount(Tournament $tournament): void
    {
        // The archive only ever shows past tournaments. The current
        // (latest) tournament stays reachable through the normal app.
        if ($tournament->getKey() === Tournament::query()->latest()->value('id')) {
            abort(404);
        }

        $this->tournament = $tournament;
    }
}; ?>

<div class="mx-auto w-full max-w-6xl space-y-8 p-4 lg:p-6">
    @php
        $phaseLabels = [
            'setup' => 'Vorbereitung',
            'group' => 'Gruppenphase',
            'placement' => 'Platzierungsspiele',
            'ko' => 'KO-Phase',
            'finished' => 'Beendet',
        ];
    @endphp

    <header class="flex flex-col gap-4">
        <div class="font-label flex items-center gap-3 text-trophy-gold">
            <a href="{{ route('archive.index') }}" wire:navigate class="inline-flex items-center gap-2 hover:text-trophy-gold/80 transition">
                <flux:icon.arrow-left class="size-4" />
                <span>Archiv</span>
            </a>
            <span class="text-stage-text-dim">·</span>
            <span class="text-stage-text-muted">{{ $phaseLabels[$tournament->status] ?? $tournament->status }}</span>
            @if ($tournament->created_at)
                <span class="text-stage-text-dim">·</span>
                <span class="font-numeric text-stage-text-muted">{{ $tournament->created_at->format('d.m.Y') }}</span>
            @endif
        </div>
        <h1 class="font-display text-stage-text text-[clamp(2rem,5vw,3.5rem)]">{{ $tournament->name }}</h1>
    </header>

    {{-- Tab switcher (Alpine, client-side). Both panels stay in the DOM so the
         embedded Livewire components mount once; only visibility toggles. --}}
    <div x-data="{ tab: 'wertung' }">
        <div role="tablist" aria-label="Ansicht wählen" class="flex gap-1 border-b border-stage-line">
            <button type="button" role="tab" data-test="tab-wertung" :aria-selected="tab === 'wertung'"
                    @click="tab = 'wertung'"
                    :class="tab === 'wertung' ? 'border-trophy-gold text-stage-text' : 'border-transparent text-stage-text-dim hover:text-stage-text-muted'"
                    class="flex items-center gap-2 border-b-2 px-4 py-3 font-label transition focus:outline-none focus-visible:ring-2 focus-visible:ring-stage-line-strong">
                <flux:icon.trophy class="size-4" />
                Wertung
            </button>
            <button type="button" role="tab" data-test="tab-statistik" :aria-selected="tab === 'statistik'"
                    @click="tab = 'statistik'"
                    :class="tab === 'statistik' ? 'border-trophy-gold text-stage-text' : 'border-transparent text-stage-text-dim hover:text-stage-text-muted'"
                    class="flex items-center gap-2 border-b-2 px-4 py-3 font-label transition focus:outline-none focus-visible:ring-2 focus-visible:ring-stage-line-strong">
                <flux:icon.chart-bar class="size-4" />
                Statistik
            </button>
        </div>

        <div x-show="tab === 'wertung'" role="tabpanel" class="space-y-12 pt-8">
            <livewire:pages::tournament-bracket :tournament-id="$tournament->id" :poll="false" :key="'archive-bracket-'.$tournament->id" />

            <section class="space-y-3">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="font-label text-stage-text-muted">Leaderboard</h2>
                    <span class="font-label text-stage-text-dim">Punkte vor Cup-Differenz vor Treffern</span>
                </div>
                <livewire:pages::leaderboard :tournament-id="$tournament->id" :poll="false" :key="'archive-leaderboard-'.$tournament->id" />
            </section>
        </div>

        <div x-show="tab === 'statistik'" x-cloak role="tabpanel" class="pt-8">
            <livewire:pages::statistics :tournament-id="$tournament->id" :poll="false" :key="'archive-statistics-'.$tournament->id" />
        </div>
    </div>
</div>
