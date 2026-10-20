<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredUpdate;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DeferredUpdate::class)]
#[Small]
final class DeferredUpdateTest extends TestCase
{
    public function testDeriveProgramRetainsRowAssignmentsBeforeOperationConstruction(): void
    {
        $table = new M\Relation\TableReference(new QualifiedName(new Name('t')));
        $column = new M\Name\ColumnUse(new Name('a'));
        $value = new M\Expression\Row([new M\Name\ColumnUse(new Name('b')), new M\Literal\NumberLiteral('2')]);
        $assignment = new M\Routine\Program\Write\DeferredColumnAssignment($column, $value);
        $write = new DeferredUpdate(null, false, false, [$table], [$assignment]);
        self::assertSame($assignment, $write->assignments[0]);
        $derivation = new \SqlSemantics\Construction\Derivation((new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->context([], true));
        $write->deriveProgram($derivation, new \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope($derivation->environment()));
        self::assertTrue($derivation->facts()->covers($table));
        self::assertTrue($derivation->facts()->covers($column));
        self::assertTrue($derivation->facts()->covers($value));
        self::assertContains(M\Expression\Problem\OperandColumns::class, array_map(static fn ($problem): string => $problem::class, $derivation->facts()->diagnostics));
    }

    public function testValueRejectsAScalarOnlyDeferredWriteWithoutAContradiction(): void
    {
        $assignment = new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a')), new M\Literal\NumberLiteral('1'));
        $this->expectExceptionMessage('A deferred UPDATE retains a fixed input, target or clause contradiction.');
        new DeferredUpdate(null, false, false, [new M\Relation\TableReference(new QualifiedName(new Name('t')))], [$assignment]);
    }

    public function testValueRejectsAnOffsetInTheRowLimit(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() UPDATE t,u SET t.a=1 LIMIT 1')->statement;
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $statement);
        $statement = $statement->body;
        self::assertInstanceOf(DeferredUpdate::class, $statement);
        $limit = new M\Query\Clause\RowLimit(new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('1'), M\Query\Clause\OffsetSpelling::Comma);
        $this->expectExceptionMessage('UPDATE limits rows without an offset.');
        new DeferredUpdate($statement->with, $statement->lowPriority, $statement->ignore, $statement->tables, $statement->assignments, limit: $limit);
    }

    public function testDeriveProgramRetainsTheCompleteWrite(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() UPDATE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET b=1");
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredUpdate::class, $operation->statement->body);
        self::assertStringContainsString('UPDATE JSON_TABLE', $operation->toString());
    }
    public function testRenderPreservesTheStoredUpdate(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() UPDATE LOW_PRIORITY IGNORE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET b=1 WHERE 0 ORDER BY b LIMIT 2");
        self::assertStringContainsString('UPDATE LOW_PRIORITY IGNORE', $operation->toString());
        self::assertStringContainsString('WHERE 0 ORDER BY b LIMIT 2', $operation->toString());
    }
}
