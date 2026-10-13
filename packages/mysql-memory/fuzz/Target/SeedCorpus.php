<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use Generator;
use RuntimeException;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\MySql\MySqlProvider;

/**
 * Replays the supplied sql-faker corpus with exactly the constraints used by its seeds checker.
 *
 * SQL Faker owns grammar coverage and seed selection. This target trusts that corpus and
 * records only execution outcomes, without measuring or requiring grammar coverage.
 */
final class SeedCorpus
{
    /**
     * The generator for the selected MySQL grammar.
     */
    public readonly MySqlProvider|SqlGenerator $provider;

    /**
     * The grammar rule the canonical corpus starts from.
     */
    public readonly string $root;

    /**
     * @var array<string, int> Counts by comparison outcome
     */
    public array $counts = ['matched' => 0, 'different' => 0, 'volatile' => 0];

    /**
     * Creates the same generator as sql-faker's bin/seeds.php check command for the initial profile.
     *
     * A focused profile replay initializes its own generator directly instead
     * of first constructing an unused default generator.
     */
    public function __construct(public readonly string $grammar, public readonly string $mode = '', public readonly bool $diagnostics = false)
    {
        $this->provider = $this->providerFor($mode, $diagnostics);
        $this->root = str_starts_with($grammar, 'mysql-5.') ? 'statement' : 'simple_statement_or_begin';
    }

    /**
     * Creates the generator for a profile supplied by SQL Faker.
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
            return new MySqlProvider($faker, $this->grammar, highNotPrecedence: $mode === 'HIGH_NOT_PRECEDENCE', pipesAsConcat: $mode === 'PIPES_AS_CONCAT');
        }
        $grammar = \SqlFaker\MySql\Grammar\MySqlGrammar::load($this->grammar)->identified();
        $context = new \SqlFaker\MySql\Generation\GenerationContext($grammar, $faker, $this->grammar, $mode === 'HIGH_NOT_PRECEDENCE', $mode === 'PIPES_AS_CONCAT');

        return new SqlGenerator($grammar, $faker, $context->lexicalGrammar, null, $context->startSymbol);
    }

    /**
     * Generates every seed in filename order, without changing its bytes or generation plan.
     *
     * @return Generator<int, Seed>
     * @throws RuntimeException When the corpus is missing or an input cannot be read
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
        $constraints = GenerationPlan::fromRule($this->root)->requiringNonEmpty();
        $compiler = new BytePlanCompiler();
        foreach ($files as $file) {
            $input = file_get_contents($file);
            if ($input === false) {
                throw new RuntimeException('Cannot read seed ' . $file);
            }
            $sql = $provider->generate($compiler->compile($input, $planner, $constraints));
            $name = ($diagnostics ? 'diagnostics/' : ($mode === '' ? '' : 'modes/' . $mode . '/')) . basename($file);
            yield new Seed($name, $input, $sql, $mode, !$diagnostics);
        }
    }

    /**
     * Records a comparison and answers its status for the per-seed report.
     */
    public function record(Comparison $comparison): string
    {
        $status = $comparison->volatile ? 'volatile' : ($comparison->difference === null ? 'matched' : 'different');
        $this->counts[$status]++;

        return $status;
    }

    /**
     * Reports execution counts for the supplied inputs.
     *
     * @return array{grammar: string, root: string, inputs: int, counts: array<string, int>}
     */
    public function summary(): array
    {
        return [
            'grammar' => $this->grammar, 'root' => $this->root, 'counts' => $this->counts,
            'inputs' => array_sum($this->counts),
        ];
    }
}
