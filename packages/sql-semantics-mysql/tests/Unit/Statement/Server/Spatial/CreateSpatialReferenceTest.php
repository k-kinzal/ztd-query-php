<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference;

#[CoversClass(CreateSpatialReference::class)]
#[Medium]
final class CreateSpatialReferenceTest extends TestCase
{
    public function testRenderWritesIfNotExists(): void
    {
        self::assertSame("CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 9 NAME 'n' DEFINITION 'd'", (new Semantics(Dialect::MySql))->analyze("create spatial reference system if not exists 9 name 'n' definition 'd'")->toString());
    }

    public function testDeriveStatementReportsAMissingAttribute(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE SPATIAL REFERENCE SYSTEM 9 NAME 'n'");

        self::assertInstanceOf(SpatialProblem::class, $operation->facts->diagnostics[0]);
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

        new CreateSpatialReference(false, false, $number, []);
    }

}
