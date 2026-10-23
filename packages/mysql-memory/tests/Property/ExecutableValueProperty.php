<?php

declare(strict_types=1);

namespace Tests\Property;

use JsonException;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Operation;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Bindings;
use Tests\Generator\Profiles;
use Tests\Generator\Samples;

/**
 * Constructor invariants and direct consumption share the same generated objects.
 *
 * @phpstan-require-extends TestCase
 */
trait ExecutableValueProperty
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }

    /**
     * @throws JsonException When a dependency inventory cannot be read
     * Every sampled constructor accepts its declared arguments; executable roots are consumed directly.
     * @param non-negative-int $sample
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        foreach ((new Samples())->of(self::MODEL, $sample) as $value) {
            self::assertInstanceOf(self::MODEL, $value);
            $profiles = new Profiles();
            $profile = $value instanceof Operation ? $value->profile() : $profiles->select($value);
            try {
                (new Execution())->run($value, substr($profile->grammar->value, 6), (new Bindings())->nulls($value instanceof Operation ? $value->statement : $value), $profiles->mode($profile));
            } catch (SqlError $error) {
                self::assertTrue($error->signalled !== null || !in_array($error->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
            }
        }
    }
}
