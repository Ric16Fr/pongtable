<?php

namespace App\Services;

/**
 * Parser for the semicolon-separated group import CSV.
 *
 * Accepts two layouts:
 *  - Multi-line: first line = header (group names), following lines = teams
 *    where column index marks the group.
 *  - Flat single-line: leading cells starting with "Gruppe " are treated as
 *    the header; the remaining cells are distributed across groups in
 *    natural order (group = cellIndex modulo groupCount).
 */
class GroupCsvParser
{
    /**
     * @return array{0:int, 1:array<int,string>} [groupCount, flat list of team cells in natural order]
     */
    public function parse(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '') {
            return [0, []];
        }

        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];

        if (count($lines) > 1) {
            $header = $this->splitRow(array_shift($lines));
            $groupCount = count($header);

            $teamCells = [];
            foreach ($lines as $line) {
                foreach ($this->splitRow($line) as $cell) {
                    $teamCells[] = $cell;
                }
            }

            return [$groupCount, $teamCells];
        }

        // Flat single line: leading "Gruppe " cells are the header.
        $cells = $this->splitRow($lines[0]);
        $groupCount = 0;
        foreach ($cells as $cell) {
            if (preg_match('/^Gruppe\s/u', $cell)) {
                $groupCount++;
            } else {
                break;
            }
        }

        return [$groupCount, array_slice($cells, $groupCount)];
    }

    /**
     * Split a CSV row by `;`, trim each cell, drop empty cells.
     *
     * @return array<int,string>
     */
    private function splitRow(string $row): array
    {
        return explode(';', $row)
                |> (fn ($x) => array_map('trim', $x))
                |> (fn ($x) => array_filter($x, fn (string $cell): bool => $cell !== ''))
                |> array_values(...);
    }
}
