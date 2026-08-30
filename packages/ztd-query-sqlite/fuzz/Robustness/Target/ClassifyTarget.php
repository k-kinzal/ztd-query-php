<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Error;
use Faker\Generator;
use Fuzz\Robustness\Invariant\ClassifyDeterministicChecker;
use Fuzz\Robustness\Invariant\ClassifyNeverThrowsChecker;
use Fuzz\Robustness\Invariant\InvariantChecker;
use SqlFaker\SqliteProvider;
use ZtdQuery\Platform\Sqlite\Parse\SqliteParser;
use ZtdQuery\Platform\Sqlite\Rewrite\SqliteQueryGuard;

/**
 * The classify target.
 */
final class ClassifyTarget
{
    private Generator $faker;
    private SqliteProvider $provider;
    /** @var array<int, InvariantChecker> */
    private array $checkers;

    /**
     * Binds the instance to what it will work from.
     *
     * @param Generator $faker
     * @param SqliteProvider $provider
     */
    public function __construct(Generator $faker, SqliteProvider $provider)
    {
        $this->faker = $faker;
        $this->provider = $provider;

        $guard = new SqliteQueryGuard(new SqliteParser());
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
            fn () => $this->provider->sql(maxDepth: 8),
            fn () => $this->provider->selectStatement(maxDepth: 8),
            fn () => $this->provider->insertStatement(maxDepth: 8),
            fn () => $this->provider->updateStatement(maxDepth: 8),
            fn () => $this->provider->deleteStatement(maxDepth: 8),
            fn () => $this->provider->createTableStatement(maxDepth: 5),
            fn () => $this->provider->alterTableStatement(maxDepth: 5),
            fn () => $this->provider->dropTableStatement(maxDepth: 3),
            fn (): string => $this->provider->fullTextSearchStatement(),
        ];

        $index = ord($input[0] ?? "\0") % count($generators);
        return $generators[$index];
    }
}
