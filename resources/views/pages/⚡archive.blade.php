<?php

use App\Models\Tournament;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Archiv')] class extends Component {
    /**
     * All past tournaments — every tournament except the current (latest)
     * one, newest first. The current tournament stays reachable through
     * the normal app, so it is never listed here.
     *
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments(): Collection
    {
        $currentId = Tournament::query()->latest()->value('id');

        return Tournament::query()
            ->when($currentId, fn ($query) => $query->whereKeyNot($currentId))
            ->withCount(['matches', 'teams'])
            ->latest()
            ->get();
    }
}; ?>

<div class="mx-auto w-full max-w-4xl space-y-10 p-4 lg:p-6">
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
            <span class="block h-px w-12 bg-trophy-gold"></span>
            <span>Rückblick</span>
        </div>
        <h1 class="font-display text-stage-text text-[clamp(2rem,5vw,3.5rem)]">Archiv</h1>
        <p class="text-stage-text-muted">Vergangene Turniere – Wertung und Statistik zum Nachschlagen, schreibgeschützt.</p>
    </header>

    @if ($this->tournaments->isEmpty())
        <p class="text-stage-text-muted">Noch keine vergangenen Turniere. Sobald ein neues Turnier gestartet wird, landet das bisherige hier.</p>
    @else
        <ul class="grid grid-cols-1 gap-3">
            @foreach ($this->tournaments as $tournament)
                <li>
                    <a href="{{ route('archive.show', $tournament) }}" wire:navigate
                       class="group flex items-center justify-between gap-4 rounded-lg border border-stage-line bg-stage-surface px-5 py-4 transition hover:border-trophy-gold">
                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="truncate font-display text-xl text-stage-text">{{ $tournament->name }}</span>
                            <span class="font-numeric text-sm text-stage-text-dim">
                                {{ $tournament->created_at?->format('d.m.Y') }}
                                · {{ $tournament->teams_count }} {{ $tournament->teams_count === 1 ? 'Team' : 'Teams' }}
                                · {{ $tournament->matches_count }} {{ $tournament->matches_count === 1 ? 'Match' : 'Matches' }}
                            </span>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="font-label text-stage-text-muted">{{ $phaseLabels[$tournament->status] ?? $tournament->status }}</span>
                            <flux:icon.chevron-right class="size-5 text-stage-text-dim transition group-hover:text-trophy-gold" />
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
