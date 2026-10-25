<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredAlterTable;

#[CoversClass(DeferredAlterTable::class)]
#[Medium]
final class DeferredAlterTableTest extends TestCase
{
    public function testDeriveProgramAndDeriveRelationKeepTheTargetAndCompleteActions(): void
    {
        $operation = (new Semantics(Dialect::MySql, '5.7.44'))->analyze('CREATE PROCEDURE p() ALTER TABLE t MODIFY g INT AS(USER()), ADD a INT');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        $alter = $operation->statement->body;
        self::assertInstanceOf(DeferredAlterTable::class, $alter);
        self::assertCount(2, $alter->commands);
        self::assertTrue($operation->facts->covers($alter));
    }

    public function testRenderRetainsColumnAttributesAndActionOrder(): void
    {
        $operation = (new Semantics(Dialect::MySql, '5.7.44'))->analyze('CREATE PROCEDURE p() ALTER TABLE t MODIFY g INT AS(USER()) STORED NOT NULL, ADD a INT');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        self::assertSame('CREATE PROCEDURE p() ALTER TABLE t MODIFY g INT AS (USER()) STORED NOT NULL, ADD a INT', $operation->toString());
    }

    public function testRejectsAnOrdinaryActionList(): void
    {
        $this->expectExceptionMessage('A deferred ALTER instruction retains an intrinsic invocation-time condition.');
        new DeferredAlterTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), []);
    }
    public function testDeriveRelationResolvesTheChangedTableShape(): void
    {
        $semantics = new Semantics(Dialect::MySql, '5.7.44');
        $table = $semantics->analyze('CREATE TABLE t(g INT)');
        $operation = $semantics->analyze('CREATE PROCEDURE p() ALTER TABLE t MODIFY g INT AS(USER())', [$table]);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        $alter = $operation->statement->body;
        self::assertInstanceOf(DeferredAlterTable::class, $alter);
        $derivation = new \SqlSemantics\Construction\Derivation($operation->context);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $alter->deriveRelation($derivation, $derivation->environment())->table);
    }
}
