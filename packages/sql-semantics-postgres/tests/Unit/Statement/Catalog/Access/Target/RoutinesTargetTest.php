<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RoutinesTarget::class)]
#[Medium]
final class RoutinesTargetTest extends TestCase
{
    public function testObject(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT EXECUTE ON PROCEDURE p TO joe')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Procedure, $statement->target->object());
    }

    public function testDeriveTargetDerivesTheArgumentTypes(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT EXECUTE ON FUNCTION f(int4, varchar(3)) TO joe', [])->facts->diagnostics));
    }

    public function testRenderWritesTheKindAndTheSignatures(): void
    {
        self::assertSame('GRANT execute ON ROUTINE app.f (int4), g TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT EXECUTE ON ROUTINE app.f(int4), g TO joe')->toString());
    }

    public function testRejectsNoSignature(): void
    {
        $this->expectExceptionMessage('A grant names at least one routine.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RoutinesTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Function, []);
    }

    public function testRejectsAnotherKind(): void
    {
        $this->expectExceptionMessage('Signatures are functions, procedures or routines.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RoutinesTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Schema, []);
    }
}
