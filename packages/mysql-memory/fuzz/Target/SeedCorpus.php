<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Faker\Factory;
use RuntimeException;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\MySql\MySqlProvider;

/**
 * Builds the SQL Faker generator and root used by a supplied seed profile.
 *
 * SQL Faker owns grammar coverage and seed selection. PHP-Fuzzer supplies the inputs;
 * this configuration does not implement another replay or reporting path.
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

}
