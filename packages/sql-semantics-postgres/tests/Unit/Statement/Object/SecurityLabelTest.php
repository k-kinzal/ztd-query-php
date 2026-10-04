<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\SecurityLabel;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SecurityLabel::class)]
#[Medium]
final class SecurityLabelTest extends TestCase
{
    public function testDeriveStatementResolvesALabelledTable(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('SECURITY LABEL ON TABLE t IS NULL', []);
        self::assertSame(['Relation t does not exist.'], [$operation->facts->diagnostics[0]->message()]);
    }

    public function testRenderWritesTheProvider(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze("SECURITY LABEL FOR 'sepgsql' ON FUNCTION f(int4) IS 'l'");
        self::assertSame("SECURITY LABEL FOR 'sepgsql' ON FUNCTION f (int4) IS 'l'", $operation->toString());
    }

    public function testRejectsAKindWithoutLabels(): void
    {
        $this->expectExceptionMessage('SECURITY LABEL names the object as the grammar names objects of its kind.');
        new SecurityLabel(ObjectKind::Rule, new UnqualifiedName(new Name('r')), null);
    }
}
