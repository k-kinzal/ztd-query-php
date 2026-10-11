<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use Generator;
use RuntimeException;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\MySql\MySqlProvider;

/**
 * Replays the canonical sql-faker corpus with exactly the constraints used by its seeds checker.
 *
 * Every production reachable from statement (5.6 and 5.7) or simple_statement_or_begin is in the
 * denominator. No statement is excluded or rewritten by this target. Reached, emitted and
 * successfully compared coverage are measured separately: a volatile oracle is not a match.
 */
final class SeedCorpus
{
    /**
     * Records the generator's derivations and emitted productions.
     */
    public readonly GrammarCoverage $coverage;

    /**
     * The generator for the selected MySQL grammar.
     */
    public readonly MySqlProvider|SqlGenerator $provider;

    /**
     * The grammar rule the canonical corpus starts from.
     */
    public readonly string $root;

    /**
     * @var array<string, true> Parser-confirmed alternatives of statements whose comparison matched
     */
    public array $matched = [];

    /**
     * @var array<string, true> Parser-confirmed alternatives of all compared statements
     */
    public array $parsed = [];

    /**
     * @var array<string, true> Preserved generator choices of matching statements, before retokenization
     */
    public array $matchedEmitted = [];

    /**
     * @var array<string, true> Reached alternatives of matching inputs, including replaced choices
     */
    public array $matchedReached = [];

    /**
     * @var array<string, int> Counts by comparison outcome
     */
    public array $counts = ['matched' => 0, 'different' => 0, 'volatile' => 0];

    /**
     * Creates the same generator as sql-faker's bin/seeds.php check command for the initial profile.
     *
     * A focused profile replay initializes its own generator directly instead
     * of first constructing an unused default generator and coverage inventory.
     */
    public function __construct(public readonly string $grammar, public readonly string $mode = '', public readonly bool $diagnostics = false)
    {
        $this->coverage = new GrammarCoverage();
        $this->provider = $this->providerFor($mode, $diagnostics);
        $this->root = str_starts_with($grammar, 'mysql-5.') ? 'statement' : 'simple_statement_or_begin';
    }

    /**
     * Binds one generation profile to the shared, unchanged grammar inventory.
     *
     * @throws RuntimeException When the lexical mode is unsupported
     */
    public function providerFor(string $mode, bool $diagnostics): MySqlProvider|SqlGenerator
    {
        if (!in_array($mode, ['', 'HIGH_NOT_PRECEDENCE', 'PIPES_AS_CONCAT'], true)) {
            throw new RuntimeException('Unsupported corpus SQL mode: ' . $mode);
        }
        $faker = Factory::create();
        if (!$diagnostics) {
            return new MySqlProvider($faker, $this->grammar, $this->coverage, $mode === 'HIGH_NOT_PRECEDENCE', $mode === 'PIPES_AS_CONCAT');
        }
        $grammar = \SqlFaker\MySql\Grammar\MySqlGrammar::load($this->grammar)->identified();
        $context = new \SqlFaker\MySql\Generation\GenerationContext($grammar, $faker, $this->grammar, $mode === 'HIGH_NOT_PRECEDENCE', $mode === 'PIPES_AS_CONCAT');

        return new SqlGenerator($grammar, $faker, $context->lexicalGrammar, null, $context->startSymbol, $this->coverage, $grammar);
    }

    /**
     * Generates every seed in filename order, without changing its bytes or generation plan.
     *
     * @return Generator<int, Seed>
     * @throws RuntimeException When the corpus is missing or an input cannot be read
     * @throws \SqlParser\Lexer\LexicalException When generated SQL cannot be tokenized
     * @throws \SqlParser\Parser\SyntaxException When generated SQL cannot be parsed
     */
    public function inputs(string $directory): Generator
    {
        yield from $this->profileInputs($directory);
        $profiles = glob($directory . '/modes/*', GLOB_ONLYDIR);
        if ($profiles === false) {
            throw new RuntimeException('Cannot read SQL mode profiles in ' . $directory);
        }
        foreach ($profiles as $profile) {
            yield from $this->profileInputs($profile, basename($profile));
        }
        if (is_dir($directory . '/diagnostics')) {
            yield from $this->profileInputs($directory . '/diagnostics', diagnostics: true);
        }
    }

    /**
     * Replays a profile directory or individual seed with the explicitly supplied lexical mode.
     *
     * @return Generator<int, Seed>
     * @throws RuntimeException When the profile or an input is unavailable
     * @throws \SqlParser\Lexer\LexicalException When generated SQL cannot be tokenized
     * @throws \SqlParser\Parser\SyntaxException When generated SQL cannot be parsed
     */
    public function profileInputs(string $directory, string $mode = '', bool $diagnostics = false): Generator
    {
        if (!in_array($mode, ['', 'HIGH_NOT_PRECEDENCE', 'PIPES_AS_CONCAT'], true)) {
            throw new RuntimeException('Unsupported corpus SQL mode: ' . $mode);
        }
        $files = is_file($directory) ? [$directory] : glob($directory . '/*.txt');
        if ($files === false || $files === []) {
            throw new RuntimeException('No sql-faker seeds found in ' . $directory);
        }
        sort($files);
        $provider = $mode === $this->mode && $diagnostics === $this->diagnostics ? $this->provider : $this->providerFor($mode, $diagnostics);
        $planner = $provider->planner();
        $parsed = new ParsedCoverage(new \SqlParser\MySql\MySqlParser($this->grammar, \SqlParser\MySql\SqlMode::fromString($mode)), $this->coverage->inventory());
        $constraints = GenerationPlan::fromRule($this->root)->requiringNonEmpty();
        $compiler = new BytePlanCompiler();
        foreach ($files as $file) {
            $input = file_get_contents($file);
            if ($input === false) {
                throw new RuntimeException('Cannot read seed ' . $file);
            }
            $sql = $provider->generate($compiler->compile($input, $planner, $constraints));
            $trace = $this->coverage->lastGeneration();
            assert($trace !== null);

            $name = ($diagnostics ? 'diagnostics/' : ($mode === '' ? '' : 'modes/' . $mode . '/')) . basename($file);
            yield new Seed($name, $input, $sql, $trace['reachedIds'], $trace['emittedIds'], $parsed->ids($sql), $mode, !$diagnostics);
        }
    }

    /**
     * Records a comparison and answers its status for the per-seed report.
     */
    public function record(Seed $seed, Comparison $comparison): string
    {
        $status = $comparison->volatile ? 'volatile' : ($comparison->difference === null ? 'matched' : 'different');
        $this->counts[$status]++;
        $this->parsed += array_fill_keys($seed->parsed, true);
        if ($status === 'matched') {
            $this->matched += array_fill_keys($seed->parsed, true);
            $this->matchedEmitted += array_fill_keys($seed->emitted, true);
            $this->matchedReached += array_fill_keys($seed->reached, true);
        }

        return $status;
    }

    /**
     * Lists exactly the statement-root denominator used by sql-faker's canonical corpus.
     *
     * @return list<string>
     */
    public function denominator(): array
    {
        $inventory = $this->coverage->inventory();
        $rules = $inventory->reachableRules($this->root);

        return array_keys(array_filter($inventory->entries, static fn (array $entry): bool => isset($rules[$entry['rule']])));
    }

    /**
     * Reports coverage counts and the alternatives that remain unverified, without rounding a partial rate to 100%.
     *
     * @return array{grammar: string, root: string, counts: array<string, int>, total: int, reached: int, emitted: int, parsed: int, matched: int, matchedEmitted: int, matchedReached: int, unreached: list<string>, unverified: list<string>}
     */
    public function summary(): array
    {
        $target = $this->denominator();
        $current = $this->coverage->snapshot()['current'];

        return [
            'grammar' => $this->grammar, 'root' => $this->root, 'counts' => $this->counts,
            'total' => count($target),
            'reached' => count(array_intersect($target, $current['reachedIds'])),
            'emitted' => count(array_intersect($target, $current['emittedIds'])),
            'parsed' => count(array_intersect($target, array_keys($this->parsed))),
            'matched' => count(array_intersect($target, array_keys($this->matched))),
            'matchedEmitted' => count(array_intersect($target, array_keys($this->matchedEmitted))),
            'matchedReached' => count(array_intersect($target, array_keys($this->matchedReached))),
            'unreached' => array_values(array_diff($target, $current['reachedIds'])),
            'unverified' => array_values(array_diff($target, array_keys($this->matched))),
        ];
    }
}
