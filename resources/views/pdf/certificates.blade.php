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

    Typography — the real brand fonts, embedded for dompdf:
      dompdf cannot read woff2 nor variable fonts, so static TTF instances
      (resources/fonts/pdf/*.ttf, derived from the same Inter / JetBrains
      Mono sources the web app ships) are inlined as base64 data URIs. Each
      weight gets its OWN family name and is always used at font-weight
      normal — dompdf does not synthesise bold, so a single registered
      weight per family is the reliable pattern. Inter's display optical
      size (opsz 32) is baked into the hero weight; numerics are set in
      JetBrains Mono per DESIGN.md's Tabular-Numeric Rule.

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

    // One dedicated family per static weight (dompdf weight-matching is
    // unreliable; a unique family + font-weight:normal is bulletproof).
    $pdfFonts = [
        'InterBody' => 'fonts/pdf/inter-400.ttf',     // footer / running text
        'InterLabel' => 'fonts/pdf/inter-600.ttf',    // tracked uppercase labels
        'InterDisplay' => 'fonts/pdf/inter-800.ttf',  // hero display (opsz 32)
        'Mono' => 'fonts/pdf/jetbrainsmono-700.ttf',  // numerics
    ];
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }

        @foreach ($pdfFonts as $family => $path)
        @font-face {
            font-family: '{{ $family }}';
            font-weight: normal;
            font-style: normal;
            src: url(data:font/truetype;base64,{{ base64_encode(file_get_contents(resource_path($path))) }});
        }
        @endforeach

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'InterBody', sans-serif;
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
            font-family: 'InterLabel';
            font-weight: normal;
            font-size: 10.5pt;
            letter-spacing: 4pt;
            text-transform: uppercase;
            color: #837A6B; /* stage-text-dim */
        }

        .tournament {
            font-family: 'InterDisplay';
            font-weight: normal;
            font-size: 30pt;
            letter-spacing: 1pt;
            color: #C0820C; /* trophy-gold, a touch deepened for paper */
            line-height: 1.1;
        }

        .team {
            font-family: 'InterDisplay';
            font-weight: normal;
            font-size: 46pt;
            letter-spacing: -1pt;
            line-height: 1.0;
            color: #19150F;
        }

        .place-num {
            font-family: 'Mono';
            font-weight: normal;
            font-size: 92pt;
            line-height: 1.0;
        }
        .place-label {
            font-family: 'InterLabel';
            font-weight: normal;
            font-size: 13pt;
            letter-spacing: 6pt;
            text-transform: uppercase;
            color: #4D473C; /* stage-text-muted */
        }

        /* The gold hero for special prizes — the prize name (or the
           Wurfkönig's feat), set like the tournament name. */
        .award {
            font-family: 'InterDisplay';
            font-weight: normal;
            font-size: 34pt;
            letter-spacing: 0.5pt;
            line-height: 1.1;
            color: #C0820C; /* trophy-gold, deepened for paper */
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
            font-family: 'InterLabel';
            letter-spacing: 1.5pt;
            text-transform: uppercase;
        }
        .footer .powered .wm {
            font-family: 'InterDisplay';
            text-transform: lowercase;
            letter-spacing: 0.5pt;
            color: #4D473C;
        }
        .footer .cups { float: right; }
        .footer .cups-num {
            font-family: 'Mono';
            color: #4D473C;
        }
    </style>
</head>
<body>
    @foreach ($certificates as $certificate)
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

                @if ($certificate['type'] === 'cup_king')
                    <p class="eyebrow">hat</p>
                    <div class="gap-sm"></div>
                    <h1 class="team">{{ $certificate['player'] }}</h1>
                    <div class="gap-lg"></div>
                    <div class="award">die meisten Becher</div>
                    <div class="gap-sm"></div>
                    <p class="place-label">getroffen</p>
                @elseif ($certificate['type'] === 'special')
                    <p class="eyebrow">hat das Team</p>
                    <div class="gap-sm"></div>
                    <h1 class="team">{{ $certificate['team'] }}</h1>
                    <div class="gap-lg"></div>
                    <p class="eyebrow">den Sonderpreis</p>
                    <div class="gap-sm"></div>
                    <div class="award">{{ $certificate['award'] }}</div>
                    <div class="gap-sm"></div>
                    <p class="place-label">erhalten</p>
                @else
                    @php $placeColor = $goldByRank[$certificate['rank']] ?? $neutralPlace; @endphp
                    <p class="eyebrow">hat das Team</p>
                    <div class="gap-sm"></div>
                    <h1 class="team">{{ $certificate['team'] }}</h1>
                    <div class="gap-lg"></div>
                    <p class="eyebrow">den</p>
                    <div class="place-num" style="color: {{ $placeColor }};">{{ $certificate['rank'] }}.</div>
                    <div class="gap-sm"></div>
                    <p class="place-label">Platz erreicht</p>
                @endif

                <div class="gap-md"></div>
                <div class="rule"></div>
            </div>

            <div class="footer">
                <span class="powered">powered by <span class="wm">pongtable</span></span>
                <span class="cups">Die Teams mussten sich durch <span class="cups-num">{{ $totalCups }}</span> Becher kämpfen</span>
            </div>
        </div>
    @endforeach
</body>
</html>
