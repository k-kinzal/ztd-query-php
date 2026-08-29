<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use Faker\Generator;
use Fuzz\Robustness\Invariant\ClassifyDeterministicChecker;
use Fuzz\Robustness\Invariant\ClassifyNeverThrowsChecker;
use Fuzz\Robustness\Invariant\InvariantChecker;
use SqlFaker\MySqlProvider;
use SqlFaker\MySqlStatementProvider;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlQueryGuard;

/**
 * The classify target.
 */
final class ClassifyTarget
{
    private Generator $faker;
    private MySqlProvider $provider;

    /** @readonly */
    private MySqlStatementProvider $statements;
    /** @var array<int, InvariantChecker> */
    private array $checkers;

    /**
     * Binds the instance to what it will work from.
     *
     * @param Generator $faker
     * @param MySqlProvider $provider
     */
    public function __construct(Generator $faker, MySqlProvider $provider)
    {
        $this->faker = $faker;
        $this->provider = $provider;
        $this->statements = new MySqlStatementProvider($faker);

        $guard = new MySqlQueryGuard(new MySqlParser());
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
     * @return callable(): string
     */
    public function selectGenerator(string $input): callable
    {
        $generators = [
            fn () => $this->provider->sql(maxDepth: 8),
            fn () => $this->statements->selectStatement(maxDepth: 8),
            fn () => $this->statements->insertStatement(maxDepth: 8),
            fn () => $this->statements->updateStatement(maxDepth: 8),
            fn () => $this->statements->deleteStatement(maxDepth: 8),
            fn () => $this->statements->createTableStatement(maxDepth: 5),
            fn () => $this->statements->alterTableStatement(maxDepth: 5),
            fn () => $this->statements->replaceStatement(maxDepth: 5),
            fn () => $this->statements->truncateStatement(maxDepth: 3),
            fn (): string => $this->statements->partitionSelectStatement(),
            fn (): string => $this->statements->loadDataStatement(maxDepth: 8),
            fn (): string => $this->statements->fullTextSearchStatement(),
        ];

        $index = ord($input[0] ?? "\0") % count($generators);
        return $generators[$index];
    }
}
