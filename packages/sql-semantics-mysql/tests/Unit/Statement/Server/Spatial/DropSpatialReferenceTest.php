<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference;

#[CoversClass(DropSpatialReference::class)]
#[Medium]
final class DropSpatialReferenceTest extends TestCase
{
    public function testRenderWritesTheIdentifier(): void
    {
        self::assertSame('DROP SPATIAL REFERENCE SYSTEM 9', (new Semantics(Dialect::MySql))->analyze('drop spatial reference system 9')->toString());
    }

    public function testDeriveStatementLeavesCatalogConditionsToTheConsumer(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP SPATIAL REFERENCE SYSTEM 9');
        self::assertSame([], $operation->diagnostics());
    }

    public function testInputPreservesARequestForAnUnmodifiableIdentifier(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP SPATIAL REFERENCE SYSTEM 0');
        self::assertSame('DROP SPATIAL REFERENCE SYSTEM 0', $operation->toString());
        self::assertInstanceOf(DropSpatialReference::class, $operation);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::IdentifierZero, $operation->problem?->rule);
        self::assertSame($operation->problem->message(), $operation->diagnostics()[0]->message());
    }
    /**
     * @return iterable<string, array{\SqlSemantics\Platform\MySql\Statement\Literal\Numeral}>
     */
    public static function providerNonIntegerTokens(): iterable
    {
        foreach (['1.5', '1e2', '18446744073709551616'] as $number) {
            yield $number => [new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral($number)];
        }
    }

    #[DataProvider('providerNonIntegerTokens')]
    public function testIdentifierRequiresAnIntegerToken(\SqlSemantics\Platform\MySql\Statement\Literal\Numeral $number): void
    {
        $this->expectExceptionMessage('A spatial reference identifier is an integer token.');

        new DropSpatialReference(false, $number);
    }

    public function testRequestRetainsTheUnmodifiableIdentifier(): void
    {
        $statement = new DropSpatialReference(true, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('0'));
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule::IdentifierZero, $statement->problem?->rule);
    }

}
