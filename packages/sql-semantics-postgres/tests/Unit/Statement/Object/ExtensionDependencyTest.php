<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\ExtensionDependency;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ExtensionDependency::class)]
#[Medium]
final class ExtensionDependencyTest extends TestCase
{
    public function testDeriveStatementRecordsNothingForATrigger(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER TRIGGER g ON t DEPENDS ON EXTENSION e');
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesNo(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER FUNCTION f NO DEPENDS ON EXTENSION e');
        self::assertSame('ALTER FUNCTION f NO DEPENDS ON EXTENSION e', $operation->toString());
    }

    public function testRejectsAKindWithoutDependencies(): void
    {
        $this->expectExceptionMessage('DEPENDS ON EXTENSION names the object as the grammar names objects of its kind.');
        new ExtensionDependency(ObjectKind::Schema, new UnqualifiedName(new Name('s')), new Name('e'));
    }

    public function testDeriveStatementReportsAnotherKind(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['"v" is not an index'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('ALTER INDEX v DEPENDS ON EXTENSION e', $context)->facts->diagnostics));
    }
}
