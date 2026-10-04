<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference;

#[CoversClass(DropSpatialReference::class)]
#[Medium]
final class DropSpatialReferenceTest extends TestCase
{
    public function testRenderWritesTheIdentifier(): void
    {
        self::assertSame('DROP SPATIAL REFERENCE SYSTEM 9', (new Semantics(Dialect::MySql))->analyze('drop spatial reference system 9')->toString());
    }

    public function testDeriveStatementReportsIdentifierZero(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP SPATIAL REFERENCE SYSTEM 0');

        self::assertInstanceOf(SpatialProblem::class, $operation->facts->diagnostics[0]);
    }
}
