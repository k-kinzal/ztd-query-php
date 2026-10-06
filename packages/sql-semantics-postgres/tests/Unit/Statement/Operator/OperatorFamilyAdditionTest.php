<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\OperatorFamilyAddition;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(OperatorFamilyAddition::class)]
#[Medium]
final class OperatorFamilyAdditionTest extends TestCase
{
    public function testDeriveStatementReportsAStorageType(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER OPERATOR FAMILY f USING btree ADD STORAGE int4');
        self::assertEquals([new ObjectProblem(ObjectProblemKind::StorageInFamily)], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheItems(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER OPERATOR FAMILY f USING btree ADD OPERATOR 1 < (int4, int8), FUNCTION 1 f(int4, int8)');
        self::assertSame('ALTER OPERATOR FAMILY f USING btree ADD OPERATOR 1 < (int4, int8), FUNCTION 1 f (int4, int8)', $operation->toString());
    }

    public function testRejectsNoItem(): void
    {
        $this->expectExceptionMessage('ADD names at least one item.');
        new OperatorFamilyAddition(new DottedName([new Name('f')]), new Name('btree'), []);
    }
}
