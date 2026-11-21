<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\AlterExtensionContents::class)]
#[Medium]
final class AlterExtensionContentsTest extends TestCase
{
    public function testRenderWritesTheKindAndObject(): void
    {
        self::assertSame('ALTER EXTENSION e ADD OPERATOR CLASS c USING btree', (new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect('ALTER EXTENSION e ADD OPERATOR CLASS c USING btree')->toString());
    }

    public function testRenderWritesACast(): void
    {
        self::assertSame('ALTER EXTENSION e DROP CAST (int4 AS text)', (new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect('ALTER EXTENSION e DROP CAST (int4 AS text)')->toString());
    }

    public function testDeriveStatementDerivesTheObject(): void
    {
        self::assertSame([], (new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect('ALTER EXTENSION e ADD TYPE numeric(4, 2)')->facts->diagnostics);
    }

    public function testRejectsAKindAlterExtensionCannotAddress(): void
    {
        $this->expectExceptionMessage('ALTER EXTENSION cannot address an object of this kind.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\AlterExtensionContents(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('e'), \SqlSemantics\Platform\PostgreSql\Statement\Option\AddOrDrop::Add, \SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind::Trigger, new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')]));
    }

    public function testDeriveStatementReportsAnotherKind(): void
    {
        $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
        $context = [$semantics->inspect('CREATE MATERIALIZED VIEW m AS SELECT 1 AS a')];
        self::assertSame(['"m" is not a view'], array_map(static fn ($problem): string => $problem->message(), $semantics->inspect('ALTER EXTENSION e ADD VIEW m', $context)->facts->diagnostics));
    }
}
