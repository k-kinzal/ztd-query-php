<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Target;

use Faker\Generator;
use SqlFaker\MySqlProvider;

/**
 * The classify target.
 */
final class ClassifyTarget
{
    private Generator $faker;
    private MySqlProvider $provider;
    /**
     * Answers the generator this input should be fuzzed through.
     *
     * @param string $input Bytes the fuzzer handed over
     *
     * @return callable(): string The generator to draw a statement from
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
            fn () => $this->provider->replaceStatement(maxDepth: 5),
            fn () => $this->provider->truncateStatement(maxDepth: 3),
            fn (): string => $this->provider->partitionSelectStatement(),
            fn (): string => $this->provider->loadDataStatement(maxDepth: 8),
            fn (): string => $this->provider->fullTextSearchStatement(),
        ];

        $index = ord($input[0] ?? "\0") % count($generators);
        return $generators[$index];
    }
}
