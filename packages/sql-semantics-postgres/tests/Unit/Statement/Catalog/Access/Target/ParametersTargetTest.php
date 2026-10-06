<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\ParametersTarget::class)]
#[Medium]
final class ParametersTargetTest extends TestCase
{
    public function testObject(): void
    {
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Parameter, (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\ParametersTarget([new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\ParameterName([new \SqlSemantics\Statement\Identifier\Name('work_mem')])]))->object());
    }

    public function testDeriveTargetResolvesNothing(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SET, ALTER SYSTEM ON PARAMETER work_mem TO joe', [])->facts->diagnostics));
    }

    public function testRenderWritesTheParameters(): void
    {
        self::assertSame('GRANT ALTER SYSTEM ON PARAMETER work_mem, a.b TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT ALTER SYSTEM ON PARAMETER work_mem, a.b TO joe')->toString());
    }

    public function testRejectsNoParameter(): void
    {
        $this->expectExceptionMessage('A grant names at least one parameter.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\ParametersTarget([]);
    }
}
