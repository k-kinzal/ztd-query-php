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
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Choice\BytePlanEncoder;
use SqlFaker\Generation\Choice\PlanBuilder;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Coverage\GrammarCoverageInventory;
use SqlFaker\Generation\Derivation\TerminationAnalyzer;
use SqlFaker\Generation\Exception\GenerationException;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
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
 *   php bin/seeds.php build [--output <dir>] [--tag <version>]... [--all] [--cap <expansions>]
 *   php bin/seeds.php check [--input <dir>] [--tag <version>]... [--all]
 *
 * Seeds are written to <dir>/<database>/<version>/<rule>-<ordinal>.txt with a manifest at
 * <dir>/<database>/<version>.json; <dir> defaults to the package's seeds directory. Without --tag or
 * --all, the default release of each database is used.
 *
 * A seed is an array{input: string, target: ?string, budget: int, sql: string, reached: list<string>, emitted: list<string>}
 * where target is "rule#ordinal" for a synthesized seed and null for a replayed input, and reached and emitted are
 * coverage production IDs. A corpus is an array{root: string, seeds: list<seed>, targets: array<string, string>,
 * failures: array<string, string>} where targets maps every production ID reachable from the root to its label.
 */

function seedsParseArguments(array $argv): array
{
    $options = ['command' => $argv[1] ?? '', 'tags' => [], 'all' => false, 'directory' => dirname(__DIR__) . '/seeds', 'cap' => BytePlanCompiler::MAXIMUM_BUDGET];
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
    if (!in_array($options['command'], ['build', 'check'], true)) {
        fwrite(STDERR, "Usage:\n  php bin/seeds.php build [--output <dir>] [--tag <version>]... [--all] [--cap <expansions>]\n  php bin/seeds.php check [--input <dir>] [--tag <version>]... [--all]\n");
        exit(2);
    }
    if ($options['all']) {
        $options['tags'] = [...SqlVersion::names('mysql'), ...SqlVersion::names('postgresql'), ...SqlVersion::names('sqlite')];
    } elseif ($options['tags'] === []) {
        $options['tags'] = [SqlVersion::resolve('mysql')->name, SqlVersion::resolve('postgresql')->name, SqlVersion::resolve('sqlite')->name];
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
 * Labels every production reachable from the root by its coverage ID, in grammar order.
 *
 * @return array<string, string>
 */
function seedsTargets(GrammarCoverageInventory $inventory, string $root): array
{
    $targets = [];
    $reachable = $inventory->reachableRules($root);
    foreach ($inventory->grammar->ruleMap as $rule => $definition) {
        foreach (isset($reachable[$rule]) ? $definition->alternatives : [] as $index => $production) {
            $targets[$inventory->id($rule, $production, $index)] = $rule . '#' . ($production->ordinal ?? $index);
        }
    }
    return $targets;
}

/**
 * Indexes which rules mention each rule in an alternative that can finish, so paths never enter a dead end.
 *
 * @return array<string, array<string, true>>
 */
function seedsContainers(Grammar $grammar, TerminationAnalyzer $analyzer): array
{
    $containers = [];
    foreach ($grammar->ruleMap as $name => $rule) {
        foreach ($rule->alternatives as $production) {
            if (!$analyzer->isProductionViable($production)) {
                continue;
            }
            foreach ($production->nonTerminalNames() as $inner) {
                $containers[$inner][$name] = true;
            }
        }
    }
    return $containers;
}

/**
 * Counts the fewest expansions from every rule that can reach the named rule; rules that cannot are absent.
 *
 * @return array<string, int>
 */
function seedsDistances(array $containers, string $rule): array
{
    $distances = [$rule => 0];
    $queue = [$rule];
    for ($next = 0; isset($queue[$next]); ++$next) {
        foreach (array_keys($containers[$queue[$next]] ?? []) as $outer) {
            if (!isset($distances[$outer])) {
                $distances[$outer] = $distances[$queue[$next]] + 1;
                $queue[] = $outer;
            }
        }
    }
    return $distances;
}

/**
 * Picks the candidate a derivation past its depth would take: fewest expansions, then fewest tokens.
 *
 * @param non-empty-list<Production> $candidates
 */
function seedsCheapest(TerminationAnalyzer $analyzer, array $candidates): int
{
    $selected = 0;
    $fewestSteps = PHP_INT_MAX;
    $fewestTokens = PHP_INT_MAX;
    foreach ($candidates as $index => $candidate) {
        $steps = $analyzer->estimateProductionSteps($candidate);
        $tokens = $analyzer->estimateProductionLength($candidate);
        if ($steps < $fewestSteps || ($steps === $fewestSteps && $tokens < $fewestTokens)) {
            $fewestSteps = $steps;
            $fewestTokens = $tokens;
            $selected = $index;
        }
    }
    return $selected;
}

/**
 * Steers a leftmost derivation to one production, then finishes every remaining rule as cheaply as possible.
 */
final class SeedGuide
{
    /** @var list<array{string, bool}> Pending rules in leftmost order, each with whether the target lies below it */
    private array $pending;

    public bool $reached = false;

    /** @var list<Production> Productions chosen so far, in derivation order */
    public array $productions = [];

    /**
     * @param array<string, int> $distances Expansions from each rule to the target rule
     */
    public function __construct(
        private readonly TerminationAnalyzer $analyzer,
        private readonly array $distances,
        string $root,
        private readonly string $rule,
        private readonly Production $target,
    ) {
        $this->pending = [[$root, true]];
    }

    /**
     * Chooses the next expansion for the leftmost pending rule and queues the rules the choice leaves pending.
     *
     * @param non-empty-list<Production> $candidates
     */
    public function choose(int $count, array $candidates): int
    {
        [$name, $onPath] = array_shift($this->pending) ?? throw new LogicException('The derivation expanded a rule the guide does not have pending.');
        [$index, $descent] = ($onPath ? $this->toward($name, $candidates) : null) ?? [seedsCheapest($this->analyzer, $candidates), null];
        $chosen = $candidates[$index];
        $this->productions[] = $chosen;
        $pending = [];
        foreach ($chosen->symbols as $offset => $symbol) {
            if ($symbol instanceof NonTerminal) {
                $pending[] = [$symbol->value, $offset === $descent];
            }
        }
        $this->pending = [...$pending, ...$this->pending];
        return $index;
    }

    /**
     * Takes the target when it is offered, or else the candidate holding the symbol nearest to the target rule.
     *
     * @param non-empty-list<Production> $candidates
     * @return array{int, int|null}|null The candidate and the offset of the symbol to descend into, or null when none leads on
     */
    public function toward(string $name, array $candidates): ?array
    {
        $offered = array_search($this->target, $candidates, true);
        if ($name === $this->rule && $offered !== false) {
            $this->reached = true;
            return [$offered, null];
        }
        $best = null;
        $nearest = PHP_INT_MAX;
        foreach ($candidates as $index => $candidate) {
            foreach ($candidate->symbols as $offset => $symbol) {
                $distance = $symbol instanceof NonTerminal ? $this->distances[$symbol->value] ?? PHP_INT_MAX : PHP_INT_MAX;
                if ($distance < $nearest) {
                    $nearest = $distance;
                    $best = [$index, $offset];
                }
            }
        }
        return $best;
    }
}

/**
 * Decodes one input under the corpus contract, generates from it, and reads what the generation exercised.
 */
function seedsReplay(GrammarCoverage $coverage, PlanBuilder $planner, Closure $generate, GenerationPlan $constraints, string $input, ?string $target = null): array
{
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $coverage->reset();
    $sql = $generate($plan);
    $trace = $coverage->lastGeneration();
    return ['input' => $input, 'target' => $target, 'budget' => $plan->expansionBudget() ?? 0, 'sql' => $sql, 'reached' => $trace['reachedIds'] ?? [], 'emitted' => $trace['emittedIds'] ?? []];
}

/**
 * Guides one derivation to the production, encodes its decisions, and replays the bytes through the generator.
 *
 * @return array|null The replayed seed, or null when the guided walk never reached the production
 */
function seedsSynthesize(GrammarCoverage $coverage, PlanBuilder $planner, Closure $generate, GenerationPlan $constraints, TerminationAnalyzer $analyzer, array $containers, string $rule, int $ordinal, Production $production, int $cap): ?array
{
    $guide = new SeedGuide($analyzer, seedsDistances($containers, $rule), $planner->root($constraints), $rule, $production);
    $planner->build($constraints, $cap, $guide->choose(...), static fn (): ?int => null);
    if (!$guide->reached) {
        return null;
    }
    $productions = $guide->productions;
    $step = 0;
    $replay = static function (int $count, array $candidates) use ($productions, &$step): int {
        $index = array_search($productions[$step++] ?? null, $candidates, true);
        return is_int($index) ? $index : throw new LogicException('The replay must offer every production the guided walk chose.');
    };
    $input = (new BytePlanEncoder())->encode($planner, $constraints, count($productions), $replay);
    return seedsReplay($coverage, $planner, $generate, $constraints, $input, "$rule#$ordinal");
}

/**
 * Visits productions in grammar order and keeps a seed only when it reaches a production no earlier seed did.
 */
function seedsBuildCorpus(GrammarCoverage $coverage, PlanBuilder $planner, Closure $generate, string $root, int $cap): array
{
    $inventory = $coverage->inventory();
    $analyzer = new TerminationAnalyzer($inventory->grammar);
    $containers = seedsContainers($inventory->grammar, $analyzer);
    $constraints = GenerationPlan::fromRule($root)->requiringNonEmpty();
    $targets = seedsTargets($inventory, $root);
    $reachable = $inventory->reachableRules($root);
    $covered = [];
    $seeds = [];
    $failures = [];
    foreach ($inventory->grammar->ruleMap as $rule => $definition) {
        foreach (isset($reachable[$rule]) ? $definition->alternatives : [] as $index => $production) {
            $id = $inventory->id($rule, $production, $index);
            if (isset($covered[$id])) {
                continue;
            }
            try {
                $seed = seedsSynthesize($coverage, $planner, $generate, $constraints, $analyzer, $containers, $rule, $production->ordinal ?? $index, $production, $cap);
            } catch (GenerationException | LexicalException $failure) {
                $failures[$targets[$id]] = $failure->getMessage();
                continue;
            }
            if ($seed === null || !in_array($id, $seed['reached'], true)) {
                $failures[$targets[$id]] = $seed === null ? "No walk of at most $cap expansions reached the production." : 'The replayed input selected other productions.';
                continue;
            }
            $seeds[] = $seed;
            $covered += array_fill_keys($seed['reached'], true);
        }
    }
    return ['root' => $root, 'seeds' => $seeds, 'targets' => $targets, 'failures' => $failures];
}

/**
 * Labels the targets whose IDs appear under the given key of any seed, in grammar order.
 *
 * @return list<string>
 */
function seedsCovered(array $corpus, string $key): array
{
    $ids = array_merge([], ...array_map(static fn (array $seed): array => $seed[$key], $corpus['seeds']));
    return array_values(array_intersect_key($corpus['targets'], array_fill_keys($ids, true)));
}

/**
 * Labels the targets no seed selected.
 *
 * @return list<string>
 */
function seedsUnreached(array $corpus): array
{
    return array_values(array_diff($corpus['targets'], seedsCovered($corpus, 'reached')));
}

function seedsMaximumBudget(array $corpus): int
{
    return max([0, ...array_column($corpus['seeds'], 'budget')]);
}

/**
 * Names each seed file after its target, or after its bytes when it has none; a name that differs from an
 * earlier one only by case gets a suffix, so a corpus checks out intact on case-insensitive file systems.
 *
 * @return array<string, array> File name => seed, in corpus order
 */
function seedsFiles(array $corpus): array
{
    $files = [];
    foreach ($corpus['seeds'] as $seed) {
        $name = $seed['target'] === null ? hash('sha256', $seed['input']) : str_replace('#', '-', $seed['target']);
        if (isset($files[strtolower($name)])) {
            $name .= '-' . substr(hash('sha256', $seed['input']), 0, 8);
        }
        $files[strtolower($name)] = [$name, $seed];
    }
    return array_combine(array_column($files, 0), array_column($files, 1));
}

function seedsManifest(string $database, string $tag, array $corpus): string
{
    $seeds = [];
    foreach (seedsFiles($corpus) as $name => $seed) {
        $seeds[] = ['file' => $name . '.txt', 'target' => $seed['target'], 'budget' => $seed['budget'], 'sql' => $seed['sql']];
    }
    $manifest = [
        'database' => $database,
        'version' => $tag,
        'root' => $corpus['root'],
        'contract' => [
            'compiler' => BytePlanCompiler::class,
            'plan' => "GenerationPlan::fromRule('{$corpus['root']}')->requiringNonEmpty()",
            'maximumBudget' => seedsMaximumBudget($corpus),
        ],
        'coverage' => [
            'productions' => count($corpus['targets']),
            'reached' => count(seedsCovered($corpus, 'reached')),
            'emitted' => count(seedsCovered($corpus, 'emitted')),
            'seeds' => count($corpus['seeds']),
            'unreached' => seedsUnreached($corpus),
            'failures' => $corpus['failures'],
        ],
        'seeds' => $seeds,
    ];
    return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
}

function seedsSummary(string $tag, array $corpus): void
{
    $total = count($corpus['targets']);
    $reached = count(seedsCovered($corpus, 'reached'));
    $emitted = count(seedsCovered($corpus, 'emitted'));
    printf(
        "%s: %d seeds, reached %d/%d productions (%.1f%%), emitted %d/%d (%.1f%%), largest budget %d\n",
        $tag,
        count($corpus['seeds']),
        $reached,
        $total,
        $total === 0 ? 0 : 100 * $reached / $total,
        $emitted,
        $total,
        $total === 0 ? 0 : 100 * $emitted / $total,
        seedsMaximumBudget($corpus),
    );
    foreach (seedsUnreached($corpus) as $label) {
        printf("  unreached: %s%s\n", $label, isset($corpus['failures'][$label]) ? ' (' . $corpus['failures'][$label] . ')' : '');
    }
}

function seedsBuild(string $tag, string $directory, int $cap): bool
{
    $coverage = new GrammarCoverage();
    [$database, $root, $provider] = seedsDialect($tag, $coverage);
    fwrite(STDERR, "Building seeds for $tag from $root...\n");
    $corpus = seedsBuildCorpus($coverage, $provider->planner(), $provider->generate(...), $root, $cap);
    $target = "$directory/$database/$tag";
    if (!is_dir($target) && !mkdir($target, 0777, true)) {
        fwrite(STDERR, "Cannot create $target\n");
        exit(1);
    }
    foreach (glob("$target/*.txt") ?: [] as $stale) {
        unlink($stale);
    }
    foreach (seedsFiles($corpus) as $name => $seed) {
        file_put_contents("$target/$name.txt", $seed['input']);
    }
    file_put_contents("$directory/$database/$tag.json", seedsManifest($database, $tag, $corpus));
    seedsSummary($tag, $corpus);
    return seedsUnreached($corpus) === [];
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
    $planner = $provider->planner();
    $constraints = GenerationPlan::fromRule($root)->requiringNonEmpty();
    $seeds = [];
    $broken = 0;
    foreach ($files as $file) {
        try {
            $seeds[] = seedsReplay($coverage, $planner, $provider->generate(...), $constraints, (string) file_get_contents($file));
        } catch (GenerationException | LexicalException | InvalidArgumentException $failure) {
            $broken++;
            printf("  broken: %s (%s)\n", basename($file), $failure->getMessage());
        }
    }
    $corpus = ['root' => $root, 'seeds' => $seeds, 'targets' => seedsTargets($coverage->inventory(), $root), 'failures' => []];
    seedsSummary($tag, $corpus);
    return $broken === 0 && seedsUnreached($corpus) === [];
}

$options = seedsParseArguments($argv);
$ok = true;
foreach ($options['tags'] as $tag) {
    $ok = ($options['command'] === 'build' ? seedsBuild($tag, $options['directory'], $options['cap']) : seedsCheck($tag, $options['directory'])) && $ok;
}
exit($ok ? 0 : 1);
