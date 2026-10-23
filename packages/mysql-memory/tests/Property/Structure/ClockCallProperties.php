<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use JsonException;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Metadata;

/**
 * Accepted scalar clocks execute without a source string or execution-based filtering.
 */
#[CoversNothing]
#[Medium]
final class ClockCallProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerClocks(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * @throws JsonException When the public value inventory is invalid
     * @throws \MySqlMemory\Error\SqlError When an accepted clock cannot execute
     */
    #[DataProvider('providerClocks')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesEveryAcceptedTypedClockHasAUsableScalarResult(string $release, int $seed): void
    {
        $construction = new Construction(new Choices('scalar-clock:' . $seed), new Catalogue(), new Metadata());
        $value = $construction->object(ClockCall::class, 6);
        self::assertInstanceOf(ClockCall::class, $value);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $result = (new Execution())->runIn($session, $value);

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertCount(1, $result->rows);
        self::assertCount(1, $result->rows[0]);
        self::assertIsString($result->rows[0][0]);
        self::assertSame([], $session->diagnostics->conditions);
        self::assertSame('unrelated invalid SQL', $session->text);
    }
}
