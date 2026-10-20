<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(M\Routine\Program\Write\Index\DeferredCreateIndex::class)]
#[Medium]
final class DeferredCreateIndexTest extends TestCase
{
    public function testRetainsTheExactOperandInTheIndexInstruction(): void
    {
        $part = new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]));
        $definition = new M\Routine\Program\Write\Index\DeferredCreateIndex(new Name('i'), new QualifiedName(new Name('t')), [$part]);
        self::assertSame([$part], $definition->parts);
    }

    public function testOrdinaryColumnOnlyIndexRequiresTheOrdinaryRole(): void
    {
        $this->expectExceptionMessage('A retained index instruction requires a deferred key operand.');
        new M\Routine\Program\Write\Index\DeferredCreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\ColumnPart(new Name('a'))]);
    }
    public function testDeriveProgramBindsColumnsWithinTheRetainedRow(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(a INT)');
        $operation = $semantics->analyze('CREATE PROCEDURE p() CREATE INDEX i ON t ((ROW(a, 2)))', [$table]);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(M\Routine\Program\Write\Index\DeferredCreateIndex::class, $operation->statement->body);
        $part = $operation->statement->body->parts[0];
        self::assertInstanceOf(M\Table\Key\Deferred\ExpressionPart::class, $part);
        self::assertInstanceOf(M\Expression\RowOperand::class, $part->expression);
        $items = (new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes())->parts($part->expression);
        self::assertNotNull($items);
        self::assertInstanceOf(\SqlSemantics\Statement\Operand::class, $items[0]);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $operation->facts->scalar($items[0])->resolution);
    }

    public function testDeriveRelationPreservesTheUnresolvedTarget(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $operation = $semantics->analyze('CREATE PROCEDURE p() CREATE INDEX i ON absent ((ROW(1,2)))', $semantics->context([]));
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(M\Routine\Program\Write\Index\DeferredCreateIndex::class, $operation->statement->body);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\MissingTable::class, $operation->facts->relation($operation->statement->body)->table);
    }

    public function testRenderPreservesTheCompleteStoredDefinition(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE INDEX i ON d.t ((ROW(1,2)))');
        self::assertSame('CREATE PROCEDURE p() CREATE INDEX i ON d.t ((ROW(1, 2)))', $operation->toString());
    }

}
