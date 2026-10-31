<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup;

#[CoversClass(DropLogfileGroup::class)]
#[Medium]
final class DropLogfileGroupTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('DROP LOGFILE GROUP g', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('drop logfile group g')->toString());
    }

    #[DataProvider('providerOptionCommas')]
    public function testRenderRetainsTheFirstAndLaterOptionCommas(string $version, string $sql, bool $leading): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-' . $version))->analyze($sql);
        self::assertInstanceOf(DropLogfileGroup::class, $statement);
        self::assertSame($leading, $statement->leadingComma);
        self::assertSame($sql, $statement->toString());
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerOptionCommas(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $version) {
            foreach ([[' WAIT', false], [', WAIT', true], [', WAIT, NO_WAIT', true]] as $index => [$tail, $leading]) {
                yield $version . ' ' . $index => [$version, 'DROP LOGFILE GROUP s' . $tail, $leading];
            }
        }
    }

    public function testDeriveStatementAcceptsAConsistentOptionList(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP LOGFILE GROUP g ENGINE a');

        self::assertSame([], $operation->diagnostics());
    }
}
