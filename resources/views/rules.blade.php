<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stage-bg text-stage-text antialiased">
        <div class="relative flex min-h-screen flex-col">
            <header class="sticky top-0 z-20 border-b border-stage-line bg-stage-bg">
                <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-5 lg:px-10">
                    <a href="{{ route('home') }}" class="wordmark text-xl">pongtable</a>
                    <nav class="flex items-center gap-2 text-sm">
                        <x-appearance-toggle />
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="rounded-md px-4 py-2 font-medium text-stage-text hover:bg-stage-surface transition">
                                {{ __('Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                               class="rounded-md px-4 py-2 font-medium text-stage-text-muted hover:text-stage-text hover:bg-stage-surface transition">
                                {{ __('Schiri-Login') }}
                            </a>
                        @endauth
                        <span aria-current="page"
                              class="rounded-md bg-stage-surface-2 px-4 py-2 font-medium text-stage-text">
                            {{ __('Regeln') }}
                        </span>
                    </nav>
                </div>
            </header>

            <main class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-12 px-6 pt-12 pb-16 lg:px-10 lg:pt-16">

                {{-- Hero --}}
                <section class="flex flex-col gap-5">
                    <div class="font-label flex items-center gap-3 text-trophy-gold">
                        <span class="block h-px w-12 bg-trophy-gold"></span>
                        <span>Regelwerk</span>
                    </div>
                    <h1 class="font-display text-[clamp(2.5rem,7vw,5rem)] text-stage-text">Spielregeln</h1>
                    <p class="max-w-2xl text-base leading-relaxed text-stage-text-muted lg:text-lg">
                        Das vollständige Bierpong-Regelwerk der WM — zum Nachschlagen während des Spiels.
                        Von der Anwurf-Reihenfolge über Trefferarten bis zu Platzierungsspielen und Turnierorganisation.
                    </p>

                    {{-- Jump nav: schnelles Nachschlagen --}}
                    <nav class="flex flex-wrap gap-2 pt-2">
                        @foreach ([
                            'ablauf' => 'Spielablauf',
                            'treffer' => 'Trefferarten',
                            'turnier' => 'Turnierregeln',
                            'orga' => 'Organisatorisches',
                        ] as $anchor => $label)
                            <a href="#{{ $anchor }}"
                               class="rounded-md border border-stage-line-strong px-3 py-1.5 text-sm font-medium text-stage-text-muted hover:border-stage-text hover:text-stage-text transition">
                                {{ $label }}
                            </a>
                        @endforeach
                    </nav>
                </section>

                {{-- Spielablauf --}}
                <section id="ablauf" class="scroll-mt-24 flex flex-col gap-5">
                    <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                        <h2 class="font-display text-2xl text-stage-text lg:text-3xl">Spielablauf</h2>
                        <span class="font-label text-stage-text-dim">Kurzfassung</span>
                    </div>

                    <ul class="space-y-4">
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">
                                Vor Spielbeginn spielt jeweils ein Spieler jedes Teams <span class="font-medium text-stage-text">Schere, Stein, Papier</span> — der Gewinner hat den Anwurf.
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Jedes Team hat immer den Nachwurf.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Jeder Spieler eines Teams wirft je einen Ball.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Bälle, die aufsetzen, dürfen abgewehrt werden.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">
                                Bälle, die mindestens einmal aufsetzen und danach in einem Becher landen, zählen doppelt — das Team, welches nicht geworfen hat, darf sich den weiteren Becher aussuchen.
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">
                                Getroffene Becher werden ausgetrunken, nachdem beide Spieler geworfen haben. Wird ein Becher nicht ausgetrunken und im fortlaufenden Spiel erneut getroffen, verliert das Team automatisch <span class="font-semibold text-trophy-gold">(„Death Cup")</span>.
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">
                                Treffen beide Spieler in denselben Becher, müssen 3 Becher getrunken werden: der getroffene und 2 weitere nach Wahl des Teams, welches nicht geworfen hat.
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">
                                Treffen beide Spieler einen Becher, erhält das Team die Bälle zurück und darf erneut werfen <span class="font-semibold text-trophy-gold">(„Balls Back")</span>.
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Anschließend ist das andere Team an der Reihe.</span>
                        </li>
                    </ul>
                </section>

                {{-- Trefferarten --}}
                <section id="treffer" class="scroll-mt-24 flex flex-col gap-5">
                    <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                        <h2 class="font-display text-2xl text-stage-text lg:text-3xl">Trefferarten</h2>
                        <span class="font-label text-stage-text-dim">Bierpongwürfe</span>
                    </div>

                    <ul class="space-y-4">
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">
                                Der Ellenbogen des Wurfarms muss hinter der Tischkante sein. Als Grauzone gilt der Bereich zwischen Tischkante und letzter Becherreihe — bei ständigem Ausnutzen gibt es eine Strafe (ein eigener Becher muss weg).
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Direkter Treffer zählt einfach.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Ein „Tipper" zählt zweifach (egal wie oft der Ball tippt) und darf abgewehrt werden.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">
                                Kippt ein oder mehrere Becher um, zählt dies als einfach getroffen — in diesem Fall gibt es kein „Balls Back", falls der zweite Spieler ebenfalls trifft.
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Treffen beide Spieler in denselben Becher, zählt dieser dreifach.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">
                                Treffen beide Spieler, bekommen sie die Bälle zurück und dürfen erneut werfen <span class="font-semibold text-trophy-gold">(„Balls Back")</span> — die vorher getroffenen Becher werden getrunken und entfernt.
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">
                                Berührt der Ball einen Becher und fällt in einen anderen, zählt der vorher berührte Becher ebenfalls als Treffer. Egal wie viele Becher berührt werden — es zählt immer nur der erste berührte und der getroffene Becher.
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">
                                Berührt ein Spieler einen Ball über der Platte, während das andere Team wirft, muss sein Team einen Strafbecher (freie Wahl) trinken.
                            </span>
                        </li>
                    </ul>

                    {{-- Sub-rules as cards --}}
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="rounded-lg bg-stage-surface p-6">
                            <h3 class="font-label text-trophy-gold">Trickshot</h3>
                            <ul class="mt-4 space-y-3 text-sm leading-relaxed text-stage-text-muted">
                                <li>„Trickshots" zählen einfach.</li>
                                <li>Ein „Trickshot" liegt vor, wenn der geworfene Ball über die Tischhälfte zurückkommt und man ihn aufnimmt, bevor er auf den Boden fällt.</li>
                                <li>Zulässig ist die Ausführung ausschließlich mit dem Rücken zum Tisch.</li>
                                <li>Pro Durchgang gibt es nur einen „Trickshot" pro Spieler — kommt der Ball danach erneut zurück, gibt es keinen weiteren.</li>
                            </ul>
                        </div>

                        <div class="rounded-lg bg-stage-surface p-6">
                            <h3 class="font-label text-trophy-gold">Umstellen</h3>
                            <ul class="mt-4 space-y-3 text-sm leading-relaxed text-stage-text-muted">
                                <li>Pro Spiel darf ein Team einmal seine zu treffenden Becher umstellen.</li>
                                <li>Umgestellt werden darf nur bei 3 oder 6 übrigen Bechern, wobei mindestens ein Becher an der hinteren Linie stehen muss.</li>
                                <li>Die Becher müssen alle zusammenstehen — man darf sich nicht absichtlich ein „Island" stellen.</li>
                                <li>Während eines „Balls Back" darf nicht umgestellt werden, da der Durchgang noch läuft.</li>
                            </ul>
                        </div>

                        <div class="rounded-lg bg-stage-surface p-6">
                            <h3 class="font-label text-trophy-gold">Rauspusten</h3>
                            <ul class="mt-4 space-y-3 text-sm leading-relaxed text-stage-text-muted">
                                <li>Kreist ein Ball nach dem Wurf noch im vermeintlich getroffenen Becher, darf dieser herausgepustet werden.</li>
                                <li>Ist das geschafft, muss der Ball trocken sein — leichte Spritzer durch das Pusten zählen als trocken.</li>
                                <li>Fällt ein herausgepusteter Ball in einen anderen Becher, zählen beide Becher als getroffen.</li>
                            </ul>
                        </div>

                        <div class="rounded-lg bg-stage-surface p-6">
                            <h3 class="font-label text-trophy-gold">Island</h3>
                            <ul class="mt-4 space-y-3 text-sm leading-relaxed text-stage-text-muted">
                                <li>Ein „Island" ist ein Becher, der allein und von mindestens zwei anderen Bechern getrennt steht.</li>
                                <li>Ist ein „Island" entstanden, darf man vor dem Wurf „Island" ansagen: Trifft man den alleinstehenden Becher, zählt der Treffer doppelt. Trifft man einen anderen Becher, zählt er nicht und ein eigener Becher muss getrunken werden.</li>
                                <li>Treffen beide Spieler „Island", zählen die Treffer doppelt — also 4 Becher zu trinken — und das Team erhält „Balls Back".</li>
                                <li>Bei nur noch zwei verbleibenden Bechern gibt es kein „Island" mehr.</li>
                                <li>Ein Becher gilt nur dann als „Island", wenn er von seinen Nebenbechern freigeworfen wurde — rutscht er erst im Spielverlauf weg, zählt er nicht.</li>
                            </ul>
                        </div>

                        <div class="rounded-lg bg-stage-surface p-6 md:col-span-2">
                            <h3 class="font-label text-trophy-gold">Überwerfen</h3>
                            <ul class="mt-4 space-y-3 text-sm leading-relaxed text-stage-text-muted">
                                <li>Trifft ein Spieler weder die Kante eines Bechers noch die Tischplatte, zählt der Wurf als „Airball".</li>
                                <li>Sammelt ein Team in einem laufenden Spiel 3 „Airballs", muss es einen Shot trinken und einen eigenen Becher entfernen.</li>
                                <li>Es kann unendlich viele „Airballs" geben — die Regel greift bei jedem dritten überworfenen Ball.</li>
                                <li>Ist nur noch ein Becher zu treffen, tritt diese Regel außer Kraft.</li>
                            </ul>
                        </div>
                    </div>
                </section>

                {{-- Turnierregeln --}}
                <section id="turnier" class="scroll-mt-24 flex flex-col gap-5">
                    <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                        <h2 class="font-display text-2xl text-stage-text lg:text-3xl">Turnierregeln</h2>
                        <span class="font-label text-stage-text-dim">Allgemein</span>
                    </div>

                    <ul class="space-y-4">
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Spielzeit 20 Minuten, Pausen zwischen den Spielen maximal 5–7 Minuten.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Gespielt wird in 4 Gruppen mit jeweils 4 Teams; die besten zwei Teams jeder Gruppe ziehen in die K.-o.-Runde ein.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Sieg 3 Punkte, Unentschieden 1 Punkt, Niederlage 0 Punkte.</span>
                        </li>
                    </ul>

                    {{-- Tiebreaker order --}}
                    <div class="rounded-lg bg-stage-surface p-6">
                        <h3 class="font-label text-trophy-gold">Platzierung bei Punktgleichheit</h3>
                        <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm font-medium text-stage-text">
                            <span>Punkte</span>
                            <span class="text-stage-text-dim">→</span>
                            <span>direkter Vergleich</span>
                            <span class="text-stage-text-dim">→</span>
                            <span>Torverhältnis</span>
                            <span class="text-stage-text-dim">→</span>
                            <span>getroffene Becher</span>
                        </div>
                        <p class="mt-4 text-sm leading-relaxed text-stage-text-muted">
                            Bei Gleichheit gibt es ein Entscheidungsspiel. Kommt es in der K.-o.-Phase oder in den
                            Platzierungsspielen zu einem Unentschieden, folgt <span class="font-semibold text-trophy-gold">Sudden Death</span>:
                            Es wird erneut Schere, Stein, Papier gespielt, der Gewinner hat Anwurf — es gibt keinen Nachwurf,
                            der erste Treffer gewinnt die Partie.
                        </p>
                    </div>

                    {{-- Placement matches --}}
                    <div class="rounded-lg bg-stage-surface p-6">
                        <h3 class="font-label text-trophy-gold">Platzierungsspiele</h3>
                        <p class="mt-3 text-sm leading-relaxed text-stage-text-muted">Die Plätze der Spieler, die nicht in die
                            KO-Phase einziehen, werden
                            per
                            Platzierungsspiel entschieden. Beispiel, wenn die KO-Phase aus 8 Teams besteht:</p>
                        <dl class="mt-4 grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                            @foreach ([
                                'Platz 9' => 'bester 3. der Gruppen gg. zweitbester 3.',
                                'Platz 11' => 'schlechtester 3. gg. zweitschlechtester 3.',
                                'Platz 13' => 'bester 4. gg. zweitbester 4.',
                                'Platz 15' => 'schlechtester 4. gg. zweitschlechtester 4.',
                            ] as $place => $matchup)
                                <div class="flex items-baseline gap-3 border-b border-stage-line/60 pb-2">
                                    <dt class="font-numeric shrink-0 text-sm font-semibold text-stage-text">{{ $place }}</dt>
                                    <dd class="text-sm text-stage-text-muted">{{ $matchup }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <ul class="space-y-4">
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Zulässig sind weiße Tischtennisbälle ab 1 Stern aufwärts.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Hat nach Ablauf der Spielzeit kein Team alle Becher getroffen, werden die Treffer beider Teams eingetragen (z. B. 6–8).</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Das erstgenannte Team spielt auf der Hausseite.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Die Becher müssen zwingend mit alkoholischen Getränken gefüllt werden (mind. 4,9 %).</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Kann ein Spieler eines Teams nicht antreten, darf das andere Teammitglied nur einen Ball pro Durchgang werfen.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Die Pause zwischen den Spielen wird zum Aufbau der neuen Runde genutzt (max. 5–7 Minuten); jedes Team baut seine Seite selbst und ordentlich auf.</span>
                        </li>
                    </ul>
                </section>

                {{-- Organisatorisches --}}
                <section id="orga" class="scroll-mt-24 flex flex-col gap-5">
                    <div class="flex items-baseline justify-between border-b border-stage-line pb-3">
                        <h2 class="font-display text-2xl text-stage-text lg:text-3xl">Organisatorisches</h2>
                        <span class="font-label text-stage-text-dim">Schiri & Ablauf</span>
                    </div>

                    <ul class="space-y-4">
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Während jeder Partie muss ein Schiedsrichter anwesend sein.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Ein Spieler eines anderen Teams achtet auf die Ellenbogen und zählt die „Airballs" der Spieler.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Knifflige Entscheidungen (z. B. ein herausgepusteter Ball) entscheidet der Schiri nicht — das machen die Teams unter sich aus.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Der Schiedsrichter stellt nur den Timer von 20 Minuten und trägt die getroffenen Becher der einzelnen Spieler in die Torjägerliste ein.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Die Aufbauzeit zwischen den Spielen beträgt maximal 5–7 Minuten.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-trophy-gold"></span>
                            <span class="leading-relaxed text-stage-text-muted">Am Ende des Turniers gibt es eine Siegerehrung, und der Torschützenkönig wird bekanntgegeben.</span>
                        </li>
                    </ul>
                </section>

                <div class="pt-4">
                    <a href="#" class="font-label text-stage-text-dim hover:text-stage-text transition">↑ Nach oben</a>
                </div>
            </main>

            <x-footer/>
        </div>

        @fluxScripts
    </body>
</html>
