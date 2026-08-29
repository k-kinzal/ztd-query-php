<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Faker\Generator;
use Fuzz\Robustness\Invariant\ClassifyDeterministicChecker;
use Fuzz\Robustness\Invariant\ClassifyNeverThrowsChecker;
use Fuzz\Robustness\Invariant\InvariantChecker;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\PostgreSqlStatementProvider;
use ZtdQuery\Platform\Postgres\PgSqlParser;
use ZtdQuery\Platform\Postgres\PgSqlQueryGuard;

final class ClassifyTarget
{
    private Generator $faker;
    private PostgreSqlProvider $provider;

    private PostgreSqlStatementProvider $statements;
    /** @var array<int, InvariantChecker> */
    private array $checkers;

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

    public function __invoke(string $input): void
    {
        $seed = crc32(str_pad($input, 4, "\0"));
        $this->faker->seed($seed);

        $sql = $this->selectGenerator($input)();

        foreach ($this->checkers as $checker) {
            $violation = $checker->check($sql);
            if ($violation !== null) {
                throw new \Error("Invariant violation: seed=$seed\n$violation");
            }
        }
    }

    /**
     * @return callable(): string
     */
    private function selectGenerator(string $input): callable
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
