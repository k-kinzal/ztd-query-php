<?php

/**
 * Writes the grammar coverage a fuzz target has reached for one MySQL release as a Markdown summary.
 *
 * Usage: php fuzz/report_coverage.php fuzz/coverage/<target> <release>
 * A production is reached when a generated statement took it, and emitted when its tokens were written.
 * The denominator is every production of the release's grammar, so a target that generates one kind of
 * statement reaches a part of it by design.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

[, $directory, $release] = $argv + [null, '', ''];
if (!is_dir($directory) || $release === '') {
    exit(0);
}
$coverage = new SqlFaker\Generation\Coverage\GrammarCoverage($directory);
new SqlFaker\MySql\MySqlProvider(Faker\Factory::create(), $release, $coverage);
$cumulative = $coverage->snapshot()['cumulative'];
$rate = static fn (int $count): string => sprintf('%d / %d (%.1f%%)', $count, $cumulative['total'], $cumulative['total'] === 0 ? 0.0 : 100 * $count / $cumulative['total']);
echo '### Grammar coverage of ' . basename($directory) . " on {$release}\n\n";
echo "| Productions reached | Productions emitted |\n|---|---|\n";
echo '| ' . $rate($cumulative['reached']) . ' | ' . $rate($cumulative['emitted']) . " |\n\n";
$missing = array_map(static fn (string $id): string => explode(':', $id)[1] ?? $id, array_slice($cumulative['notReachedIds'], 0, 200));
if ($missing !== []) {
    echo '<details><summary>Productions not reached (first ' . count($missing) . ")</summary>\n\n```text\n" . implode("\n", $missing) . "\n```\n</details>\n";
}
