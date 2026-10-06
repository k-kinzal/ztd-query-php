<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\SchemaContentsTarget::class)]
#[Medium]
final class SchemaContentsTargetTest extends TestCase
{
    public function testObject(): void
    {
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Routine, (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\SchemaContentsTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Routine, [new \SqlSemantics\Statement\Identifier\Name('s')]))->object());
    }

    public function testDeriveTargetResolvesNothing(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT ON ALL TABLES IN SCHEMA app TO joe', [])->facts->diagnostics));
    }

    public function testRenderWritesThePluralOfTheKind(): void
    {
        self::assertSame('GRANT ALL ON ALL PROCEDURES IN SCHEMA a, b TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT ALL ON ALL PROCEDURES IN SCHEMA a, b TO joe')->toString());
    }

    public function testRenderWritesSequences(): void
    {
        self::assertSame('REVOKE usage ON ALL SEQUENCES IN SCHEMA a FROM joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REVOKE usage ON ALL SEQUENCES IN SCHEMA a FROM joe')->toString());
    }

    public function testRejectsAKindWithoutASchemaForm(): void
    {
        $this->expectExceptionMessage('The kind can be granted for whole schemas.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\SchemaContentsTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Database, [new \SqlSemantics\Statement\Identifier\Name('s')]);
    }
}
