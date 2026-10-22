<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDelete;

#[CoversClass(DeferredDelete::class)]
#[Medium]
final class DeferredDeleteTest extends TestCase
{
    public function testValueRejectsAnOffsetInTheRowLimit(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() WITH c AS (SELECT 1) DELETE FROM c')->statement;
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $statement);
        $statement = $statement->body;
        self::assertInstanceOf(DeferredDelete::class, $statement);
        $limit = new M\Query\Clause\RowLimit(new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('1'), M\Query\Clause\OffsetSpelling::Comma);
        $this->expectExceptionMessage('DELETE limits rows without an offset.');
        new DeferredDelete($statement->with, $statement->options, $statement->table, limit: $limit);
    }

    public function testDeriveProgramPreservesTheCommonTableAndReadDependencies(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() WITH c AS(SELECT * FROM source) DELETE FROM c WHERE a>1 ORDER BY a LIMIT 2');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredDelete::class, $operation->statement->body);
        self::assertTrue($operation->facts->covers($operation->statement->body->table));
        self::assertNotEmpty($operation->facts->diagnostics);
    }

    public function testRenderKeepsSingleTableClausesInTheirOriginalRole(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() WITH c AS(SELECT 1 AS a) DELETE FROM c ORDER BY a LIMIT 2');
        self::assertSame('CREATE PROCEDURE p() WITH c AS (SELECT 1 AS a) DELETE FROM c ORDER BY a LIMIT 2', $operation->toString());
    }
    public function testPhysicalTableCannotBeUsedAsTheDeferredCommonTarget(): void
    {
        $target = new M\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        $this->expectExceptionMessage('A deferred single-table DELETE retains a target, input-column, sampling or ordering contradiction.');
        new DeferredDelete(null, [], $target);
    }

}
