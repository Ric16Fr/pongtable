<?php

use App\Services\GroupCsvParser;

beforeEach(function () {
    $this->parser = new GroupCsvParser;
});

it('parses a standard multi-line CSV with header row and team rows', function () {
    $csv = <<<'CSV'
Gruppe A;Gruppe B;Gruppe C;Gruppe D
T1;T2;T3;T4
T5;T6;T7;T8
CSV;

    [$groupCount, $teamCells] = $this->parser->parse($csv);

    expect($groupCount)->toBe(4)
        ->and($teamCells)->toBe(['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8']);
});

it('parses a flat single-line CSV detecting the header via the "Gruppe " prefix', function () {
    $csv = 'Gruppe A;Gruppe B;Gruppe C;Gruppe D;T1;T2;T3;T4;T5;T6;T7;T8';

    [$groupCount, $teamCells] = $this->parser->parse($csv);

    expect($groupCount)->toBe(4)
        ->and($teamCells)->toBe(['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8']);
});

it('handles CRLF and CR line endings the same as LF', function () {
    $crlf = "Gruppe A;Gruppe B\r\nT1;T2\r\nT3;T4";
    $cr = "Gruppe A;Gruppe B\rT1;T2\rT3;T4";

    expect($this->parser->parse($crlf))->toBe([2, ['T1', 'T2', 'T3', 'T4']])
        ->and($this->parser->parse($cr))->toBe([2, ['T1', 'T2', 'T3', 'T4']]);
});

it('strips trailing semicolons (empty trailing cells) from each row', function () {
    $csv = 'Gruppe A;Gruppe B;;;T1;T2;T3;T4;';

    [$groupCount, $teamCells] = $this->parser->parse($csv);

    expect($groupCount)->toBe(2)
        ->and($teamCells)->toBe(['T1', 'T2', 'T3', 'T4']);
});

it('trims whitespace from individual cells', function () {
    $csv = "Gruppe A ; Gruppe B \n  Team Eins ;\tTeam Zwei\t";

    [$groupCount, $teamCells] = $this->parser->parse($csv);

    expect($groupCount)->toBe(2)
        ->and($teamCells)->toBe(['Team Eins', 'Team Zwei']);
});

it('returns zero groups for an empty string', function () {
    expect($this->parser->parse(''))->toBe([0, []]);
});

it('returns zero groups for a whitespace-only string', function () {
    expect($this->parser->parse("   \n  \t  \n  "))->toBe([0, []]);
});

it('returns zero groups for a flat single line that does not start with "Gruppe "', function () {
    // Without any header markers we cannot tell groups from teams — bail out.
    [$groupCount, $teamCells] = $this->parser->parse('Team A;Team B;Team C;Team D');

    expect($groupCount)->toBe(0)
        ->and($teamCells)->toBe(['Team A', 'Team B', 'Team C', 'Team D']);
});

it('keeps Unicode characters intact (umlauts, ampersands, dots)', function () {
    $csv = "Gruppe A;Gruppe B\nMörre & Gussi;Paul N. & Niklas\nDennis & Yves H.;Felix & Lüdé";

    [$groupCount, $teamCells] = $this->parser->parse($csv);

    expect($groupCount)->toBe(2)
        ->and($teamCells)->toBe([
            'Mörre & Gussi', 'Paul N. & Niklas',
            'Dennis & Yves H.', 'Felix & Lüdé',
        ]);
});

it('preserves CSV-natural order for downstream modulo-bucket distribution', function () {
    // The service maps cell[i] → group[i % groupCount]. This test guards that
    // contract: cell order through the parser is exactly insertion order.
    $csv = "Gruppe A;Gruppe B\nA1;B1\nA2;B2\nA3;B3";

    [, $teamCells] = $this->parser->parse($csv);

    expect($teamCells)->toBe(['A1', 'B1', 'A2', 'B2', 'A3', 'B3']);
});

it('parses the User-provided real-world example correctly', function () {
    $csv = 'Gruppe A;Gruppe B;Gruppe C;Gruppe D;Renx & Philipp;Stefan & Henry;Kitty & Till;Schachi & Huschke;Dennis & Yves H.;Paul N. & Niklas;Tobi & Richard;Felo & Kluwe;Kevin & Grodon;Valle & TB;Justin & Yves M.;Franik & MB;Felix & Lude;Mörre & Gussi;John & Ede;Marvin & Luki;';

    [$groupCount, $teamCells] = $this->parser->parse($csv);

    expect($groupCount)->toBe(4)
        ->and($teamCells)->toHaveCount(16)
        ->and($teamCells[0])->toBe('Renx & Philipp')
        ->and($teamCells[15])->toBe('Marvin & Luki');
});

it('ignores ragged row widths — flat list reflects only the cells actually present', function () {
    // Row 1 has 2 teams, row 2 only one. The flat output drops empties and
    // hands the modulo decision to the consumer.
    $csv = "Gruppe A;Gruppe B\nT1;T2\nT3";

    [$groupCount, $teamCells] = $this->parser->parse($csv);

    expect($groupCount)->toBe(2)
        ->and($teamCells)->toBe(['T1', 'T2', 'T3']);
});

it('treats a single header-only line as zero teams', function () {
    [$groupCount, $teamCells] = $this->parser->parse('Gruppe A;Gruppe B;Gruppe C;Gruppe D');

    expect($groupCount)->toBe(4)
        ->and($teamCells)->toBe([]);
});

it('keeps trailing non-"Gruppe " cells as teams in the flat single-line case', function () {
    // "Gruppe Sondergruppe" still matches the prefix — but "Sondergruppe"
    // alone is treated as a team. This guards against a too-greedy prefix.
    $csv = 'Gruppe A;Gruppe B;Sondergruppe;T1;T2;T3';

    [$groupCount, $teamCells] = $this->parser->parse($csv);

    expect($groupCount)->toBe(2)
        ->and($teamCells)->toBe(['Sondergruppe', 'T1', 'T2', 'T3']);
});
