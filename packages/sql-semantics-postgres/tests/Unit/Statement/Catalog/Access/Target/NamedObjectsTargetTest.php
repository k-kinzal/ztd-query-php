<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\NamedObjectsTarget::class)]
#[Medium]
final class NamedObjectsTargetTest extends TestCase
{
    public function testObject(): void
    {
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Database, (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\NamedObjectsTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Database, [new \SqlSemantics\Statement\Identifier\Name('d')]))->object());
    }

    public function testDeriveTargetResolvesNothing(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT USAGE ON LANGUAGE plpgsql TO joe', [])->facts->diagnostics));
    }

    public function testRenderWritesTheKindAndTheNames(): void
    {
        self::assertSame('GRANT usage ON FOREIGN DATA WRAPPER w, "select" TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT usage ON FOREIGN DATA WRAPPER w, "select" TO joe')->toString());
    }

    public function testRenderWritesAForeignServer(): void
    {
        self::assertSame('GRANT usage ON FOREIGN SERVER srv TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT usage ON FOREIGN SERVER srv TO joe')->toString());
    }

    public function testRejectsAKindWithQualifiedNames(): void
    {
        $this->expectExceptionMessage('The kind has objects with unqualified names.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\NamedObjectsTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Relation, [new \SqlSemantics\Statement\Identifier\Name('d')]);
    }
}
