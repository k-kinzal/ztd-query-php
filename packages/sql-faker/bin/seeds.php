#!/usr/bin/env php
<?php

declare(strict_types=1);

foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../vendor/autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}

use Faker\Factory;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Exception\GenerationException;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Seed\CoverageSeed;
use SqlFaker\Generation\Seed\SeedCorpus;
use SqlFaker\Generation\Seed\SeedCorpusBuilder;
use SqlFaker\Grammar\Resource\SqlVersion;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\SqliteProvider;

/**
 * Builds or checks the grammar coverage seed corpora.
 *
 * A corpus holds one PHP-Fuzzer input per file. Every input decodes with BytePlanCompiler under the plan
 * GenerationPlan::fromRule(<root>)->requiringNonEmpty(), and together the inputs of a corpus select every
 * production the generator can reach from that root.
 *
 * Usage:
 *   php bin/seeds.php build --output <dir> [--tag <version>]... [--all] [--cap <expansions>]
 *   php bin/seeds.php check --input <dir> [--tag <version>]... [--all]
 *
 * Seeds are written to <dir>/<database>/<version>/<rule>-<ordinal>.txt with a manifest at
 * <dir>/<database>/<version>.json. Without --tag or --all, the default release of each database is used.
 */

function seedsParseArguments(array $argv): array
{
    $options = ['command' => $argv[1] ?? '', 'tags' => [], 'all' => false, 'directory' => null, 'cap' => SeedCorpusBuilder::MAXIMUM_BUDGET];
    for ($i = 2; $i < count($argv); $i++) {
        if ($argv[$i] === '--all') {
            $options['all'] = true;
        } elseif ($argv[$i] === '--tag' && isset($argv[$i + 1])) {
            $options['tags'][] = $argv[++$i];
        } elseif (($argv[$i] === '--output' || $argv[$i] === '--input') && isset($argv[$i + 1])) {
            $options['directory'] = $argv[++$i];
        } elseif ($argv[$i] === '--cap' && isset($argv[$i + 1])) {
            $options['cap'] = (int) $argv[++$i];
        } else {
            fwrite(STDERR, "Unknown argument: {$argv[$i]}\n");
            exit(2);
        }
    }
    if (!in_array($options['command'], ['build', 'check'], true) || $options['directory'] === null) {
        fwrite(STDERR, "Usage:\n  php bin/seeds.php build --output <dir> [--tag <version>]... [--all] [--cap <expansions>]\n  php bin/seeds.php check --input <dir> [--tag <version>]... [--all]\n");
        exit(2);
    }
    if ($options['all']) {
        $options['tags'] = [...SqlVersion::names('mysql'), ...SqlVersion::names('pg'), ...SqlVersion::names('sqlite')];
    } elseif ($options['tags'] === []) {
        $options['tags'] = [SqlVersion::resolve('mysql')->name, SqlVersion::resolve('pg')->name, SqlVersion::resolve('sqlite')->name];
    }
    return $options;
}

/**
 * Answers the corpus directory name, the statement rule the seeds start at, and a provider for the release.
 *
 * Statements start below each grammar's entry point, exactly as the fuzz targets do; the entry points
 * carry parser-internal selectors no server accepts.
 *
 * @return array{string, string, MySqlProvider|PostgreSqlProvider|SqliteProvider}
 */
function seedsDialect(string $tag, GrammarCoverage $coverage): array
{
    $faker = Factory::create();
    if (str_starts_with($tag, 'mysql-')) {
        $root = version_compare(substr($tag, strlen('mysql-')), '8.0.0', '<') ? 'statement' : 'simple_statement_or_begin';
        return ['mysql', $root, new MySqlProvider($faker, $tag, $coverage)];
    }
    if (str_starts_with($tag, 'pg-')) {
        return ['pg', 'stmt', new PostgreSqlProvider($faker, $tag, $coverage)];
    }
    if (str_starts_with($tag, 'sqlite-')) {
        return ['sqlite', 'cmd', new SqliteProvider($faker, $tag, $coverage)];
    }
    fwrite(STDERR, "Unknown grammar version: $tag\n");
    exit(2);
}

/**
 * Names each seed file after its target; a name that differs from an earlier one only by case gets a
 * suffix, so a corpus checks out intact on case-insensitive file systems.
 *
 * @return array<string, CoverageSeed> File name => seed, in corpus order
 */
function seedsFiles(SeedCorpus $corpus): array
{
    $files = [];
    foreach ($corpus->seeds as $seed) {
        $name = $seed->name();
        if (isset($files[strtolower($name)])) {
            $name .= '-' . substr(hash('sha256', $seed->input), 0, 8);
        }
        $files[strtolower($name)] = [$name, $seed];
    }
    return array_combine(array_column($files, 0), array_column($files, 1));
}

function seedsManifest(string $database, string $tag, SeedCorpus $corpus, int $maximumBudget): string
{
    $seeds = [];
    foreach (seedsFiles($corpus) as $name => $seed) {
        $seeds[] = [
            'file' => $name . '.txt',
            'target' => $seed->rule . '#' . $seed->ordinal,
            'budget' => $seed->budget,
            'sql' => $seed->sql,
        ];
    }
    $manifest = [
        'database' => $database,
        'version' => $tag,
        'root' => $corpus->root,
        'contract' => [
            'compiler' => 'SqlFaker\\Generation\\Choice\\BytePlanCompiler',
            'plan' => "GenerationPlan::fromRule('{$corpus->root}')->requiringNonEmpty()",
            'maximumBudget' => $maximumBudget,
        ],
        'coverage' => [
            'productions' => count($corpus->targets),
            'reached' => count($corpus->reached()),
            'emitted' => count($corpus->emitted()),
            'seeds' => count($corpus->seeds),
            'unreached' => $corpus->unreached(),
            'failures' => $corpus->failures,
        ],
        'seeds' => $seeds,
    ];
    return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
}

function seedsSummary(string $tag, SeedCorpus $corpus): void
{
    $total = count($corpus->targets);
    printf(
        "%s: %d seeds, reached %d/%d productions (%.1f%%), emitted %d/%d (%.1f%%), largest budget %d\n",
        $tag,
        count($corpus->seeds),
        count($corpus->reached()),
        $total,
        $total === 0 ? 0 : 100 * count($corpus->reached()) / $total,
        count($corpus->emitted()),
        $total,
        $total === 0 ? 0 : 100 * count($corpus->emitted()) / $total,
        $corpus->maximumBudget(),
    );
    foreach ($corpus->unreached() as $label) {
        printf("  unreached: %s%s\n", $label, isset($corpus->failures[$label]) ? ' (' . $corpus->failures[$label] . ')' : '');
    }
}

function seedsBuild(string $tag, string $directory, int $cap): bool
{
    $coverage = new GrammarCoverage();
    [$database, $root, $provider] = seedsDialect($tag, $coverage);
    fwrite(STDERR, "Building seeds for $tag from $root...\n");
    $builder = new SeedCorpusBuilder($coverage, $provider->planner(), $provider->generate(...));
    $corpus = $builder->build($root, $cap);
    $target = "$directory/$database/$tag";
    if (!is_dir($target) && !mkdir($target, 0777, true)) {
        fwrite(STDERR, "Cannot create $target\n");
        exit(1);
    }
    foreach (glob("$target/*.txt") ?: [] as $stale) {
        unlink($stale);
    }
    foreach (seedsFiles($corpus) as $name => $seed) {
        file_put_contents("$target/$name.txt", $seed->input);
    }
    file_put_contents("$directory/$database/$tag.json", seedsManifest($database, $tag, $corpus, $corpus->maximumBudget()));
    seedsSummary($tag, $corpus);
    return $corpus->unreached() === [];
}

function seedsCheck(string $tag, string $directory): bool
{
    $coverage = new GrammarCoverage();
    [$database, $root, $provider] = seedsDialect($tag, $coverage);
    $source = "$directory/$database/$tag";
    $files = glob("$source/*.txt") ?: [];
    if ($files === []) {
        fwrite(STDERR, "No seeds found in $source\n");
        return false;
    }
    sort($files);
    fwrite(STDERR, 'Replaying ' . count($files) . " seeds for $tag from $root...\n");
    $builder = new SeedCorpusBuilder($coverage, $provider->planner(), $provider->generate(...));
    $constraints = GenerationPlan::fromRule($root)->requiringNonEmpty();
    $seeds = [];
    $broken = 0;
    foreach ($files as $file) {
        try {
            $seeds[] = $builder->replay($constraints, (string) file_get_contents($file));
        } catch (GenerationException | LexicalException | InvalidArgumentException $failure) {
            $broken++;
            printf("  broken: %s (%s)\n", basename($file), $failure->getMessage());
        }
    }
    $corpus = new SeedCorpus($root, $seeds, $builder->targets($root));
    seedsSummary($tag, $corpus);
    return $broken === 0 && $corpus->unreached() === [];
}

$options = seedsParseArguments($argv);
$ok = true;
foreach ($options['tags'] as $tag) {
    $ok = ($options['command'] === 'build' ? seedsBuild($tag, $options['directory'], $options['cap']) : seedsCheck($tag, $options['directory'])) && $ok;
}
exit($ok ? 0 : 1);
