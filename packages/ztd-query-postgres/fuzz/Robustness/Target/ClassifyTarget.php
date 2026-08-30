<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use Faker\Generator;
use Fuzz\Robustness\Invariant\ClassifyDeterministicChecker;
use Fuzz\Robustness\Invariant\ClassifyNeverThrowsChecker;
use Fuzz\Robustness\Invariant\InvariantChecker;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\PostgreSqlStatementProvider;
use ZtdQuery\Platform\Postgres\Parse\PgSqlParser;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlQueryGuard;

/**
 * The classify target.
 */
final class ClassifyTarget
{
    private Generator $faker;
    private PostgreSqlProvider $provider;

    /** @readonly */
    private PostgreSqlStatementProvider $statements;
    /** @var array<int, InvariantChecker> */
    private array $checkers;

    /**
     * Binds the instance to what it will work from.
     *
     * @param Generator $faker
     * @param PostgreSqlProvider $provider
     */
    public function __construct(Generator $faker, PostgreSqlProvider $provider)
    {
        $this->faker = $faker;
        $this->provider = $provider;
        $this->statements = new PostgreSqlStatementProvider($faker);

        $guard = new PgSqlQueryGuard(new PgSqlParser());
        $this->checkers = [
            new ClassifyNeverThrowsChecker($guard),
            new ClassifyDeterministicChecker($guard),
        ];
    }

    /**
     * @throws Error
     */
    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);

        $sql = $this->selectGenerator($input)();

        foreach ($this->checkers as $checker) {
            $violation = $checker->check($sql);
            if ($violation !== null) {
                throw new Error("Invariant violation: seed=$seed\n$violation");
            }
        }
    }

    /**
     * Answers the generator this input should be fuzzed through.
     *
     * @param string $input The input
     *
     * @return callable(): string
     */
    public function selectGenerator(string $input): callable
    {
        $generators = [
            fn (): string => $this->provider->sql(maxDepth: 8),
            fn (): string => $this->statements->selectStatement(maxDepth: 8),
            fn (): string => $this->statements->insertStatement(maxDepth: 8),
            fn (): string => $this->statements->updateStatement(maxDepth: 8),
            fn (): string => $this->statements->deleteStatement(maxDepth: 8),
            fn (): string => $this->statements->createTableStatement(maxDepth: 5),
            fn (): string => $this->statements->alterTableStatement(maxDepth: 5),
            fn (): string => $this->statements->dropTableStatement(maxDepth: 3),
            fn (): string => $this->statements->partitionOfStatement(),
            fn (): string => $this->statements->tableSampleStatement(),
            fn (): string => $this->statements->doStatement(),
            fn (): string => $this->statements->mergeStatement(),
            fn (): string => $this->statements->copyStatement(maxDepth: 8),
            fn (): string => $this->statements->partialIndexUpsertStatement(),
            fn (): string => $this->statements->createDomainStatement(maxDepth: 8),
            fn (): string => $this->statements->domainDmlStatement(),
            fn (): string => $this->statements->fullTextSearchStatement(),
        ];

        $index = ord($input[0] ?? "\0") % count($generators);
        return $generators[$index];
    }
}
