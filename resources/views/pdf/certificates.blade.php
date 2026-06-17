{{--
    Tournament certificates — one A4 page per team, optimised for printing
    on plain WHITE paper. Self-contained inline CSS only: this view is
    rendered by the dompdf driver, which has no access to the compiled
    Tailwind theme and supports neither oklch() nor flexbox/grid, nor the
    bunny.net web fonts (so the brand's Inter falls back to Helvetica).

    Design language follows DESIGN.md ("The Brewery Broadcast"):
      • Gold is reserved for glory — the tournament name, the podium place,
        and two hairline rules. Nothing else.
      • Warm-tinted neutrals on hue 80 (sRGB conversions of the LIGHT scheme).
      • Heavyweight display type for the team name; tracked uppercase labels.
      • Two-corner framing: faint red/blue cup crescents bleeding off the
        left and right edges.

    Expected data:
      string $tournamentName
      int    $totalCups
      array  $certificates  list of ['team' => string, 'rank' => int]
--}}
@php
    // Gold ladder for the podium (rank → colour). Outside the top 3 the place
    // number drops to a neutral roast tone — gold stays a rarity.
    $goldByRank = [
        1 => '#DA8B00', // trophy-gold
        2 => '#BB5F00', // trophy-gold-deep
        3 => '#8A5A12', // warm bronze-gold
    ];
    $neutralPlace = '#3B362E';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Helvetica', sans-serif; /* Inter proxy in dompdf */
            color: #19150F; /* stage-text, deep roast */
        }

        .sheet {
            position: relative;
            width: 210mm;
            height: 297mm;
            overflow: hidden;
            background: #FFFFFF;
        }

        .sheet.break { page-break-after: always; }

        /* Two-corner framing — faint cup crescents pushed far off the page
           edges so only a sliver bleeds in. Soft tints stand in for the
           "with opacity" look and stay light on ink. */
        .corner {
            position: absolute;
            width: 175mm;
            height: 175mm;
            border-radius: 50%;
            top: 61mm;
        }
        .corner-blue { left: -120mm; background: #DCF1FF; }  /* blue-corner, faint */
        .corner-red  { right: -120mm; background: #FFE4E0; } /* red-corner, faint */

        .content {
            position: absolute;
            top: 46mm;
            left: 24mm;
            right: 24mm;
            text-align: center;
        }

        /* Short centred gold hairline — the only structural ornament. */
        .rule {
            width: 30mm;
            height: 0.5mm;
            background: #DA8B00;
            margin: 0 auto;
        }

        .eyebrow {
            font-size: 10.5pt;
            font-weight: bold;
            letter-spacing: 4pt;
            text-transform: uppercase;
            color: #837A6B; /* stage-text-dim */
        }

        .tournament {
            font-size: 30pt;
            font-weight: bold;
            letter-spacing: 1pt;
            color: #C0820C; /* trophy-gold, a touch deepened for paper */
            line-height: 1.1;
        }

        .team {
            font-size: 46pt;
            font-weight: bold;
            letter-spacing: -1pt;
            line-height: 1.0;
            color: #19150F;
        }

        .place-num {
            font-size: 92pt;
            font-weight: bold;
            line-height: 1.0;
        }
        .place-label {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 6pt;
            text-transform: uppercase;
            color: #4D473C; /* stage-text-muted */
        }

        /* Vertical rhythm between blocks. */
        .gap-sm { height: 6mm; }
        .gap-md { height: 9mm; }
        .gap-lg { height: 13mm; }

        .footer {
            position: absolute;
            bottom: 17mm;
            left: 22mm;
            right: 22mm;
            font-size: 9pt;
            color: #837A6B; /* stage-text-dim */
        }
        .footer .powered {
            float: left;
            letter-spacing: 1.5pt;
            text-transform: uppercase;
        }
        .footer .powered .wm {
            text-transform: lowercase;
            font-weight: bold;
            letter-spacing: 0.5pt;
            color: #4D473C;
        }
        .footer .cups { float: right; }
    </style>
</head>
<body>
    @foreach ($certificates as $certificate)
        @php
            $rank = $certificate['rank'];
            $placeColor = $goldByRank[$rank] ?? $neutralPlace;
        @endphp
        <div class="sheet @if (! $loop->last) break @endif">
            <div class="corner corner-blue"></div>
            <div class="corner corner-red"></div>

            <div class="content">
                <div class="rule"></div>
                <div class="gap-md"></div>

                <p class="eyebrow">Im Turnier</p>
                <div class="gap-sm"></div>
                <h2 class="tournament">{{ $tournamentName }}</h2>

                <div class="gap-lg"></div>

                <p class="eyebrow">hat das Team</p>
                <div class="gap-sm"></div>
                <h1 class="team">{{ $certificate['team'] }}</h1>

                <div class="gap-lg"></div>

                <p class="eyebrow">den</p>
                <div class="place-num" style="color: {{ $placeColor }};">{{ $rank }}.</div>
                <div class="gap-sm"></div>
                <p class="place-label">Platz erreicht</p>

                <div class="gap-md"></div>
                <div class="rule"></div>
            </div>

            <div class="footer">
                <span class="powered">powered by <span class="wm">pongtable</span></span>
                <span class="cups">Die Teams mussten sich durch {{ $totalCups }} Becher kämpfen</span>
            </div>
        </div>
    @endforeach
</body>
</html>
