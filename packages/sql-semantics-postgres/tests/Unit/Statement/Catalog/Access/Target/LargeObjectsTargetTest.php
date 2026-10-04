<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\LargeObjectsTarget::class)]
#[Medium]
final class LargeObjectsTargetTest extends TestCase
{
    public function testObject(): void
    {
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::LargeObject, (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\LargeObjectsTarget([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(false, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))]))->object());
    }

    public function testDeriveTargetResolvesNothing(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT, UPDATE ON LARGE OBJECT 16385 TO joe', [])->facts->diagnostics));
    }

    public function testRenderWritesTheIdentifiers(): void
    {
        self::assertSame('GRANT SELECT ON LARGE OBJECT 1, 2 TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT ON LARGE OBJECT 1, +2 TO joe')->toString());
    }

    public function testRejectsNoIdentifier(): void
    {
        $this->expectExceptionMessage('A grant names at least one large object.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\LargeObjectsTarget([]);
    }
}
