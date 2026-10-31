<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(M\Routine\Program\Write\DeferredColumnAssignment::class)]
#[Small]
final class DeferredColumnAssignmentTest extends TestCase
{
    public function testRenderRetainsAColumnAssignmentBeforeOperationConstruction(): void
    {
        $column = new M\Name\ColumnUse(new Name('a'));
        $value = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $assignment = new M\Routine\Program\Write\DeferredColumnAssignment($column, $value);
        $write = new M\Routine\Program\Write\DeferredInsertRows(new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget(new QualifiedName(new Name('t')))), [new M\Dml\Insert\InsertedRow([new M\Literal\NumberLiteral('3')])], onDuplicate: [$assignment]);
        $out = new \SqlSemantics\Rendering\Output((new \SqlSemantics\Platform\MySql\Platform())->codec((new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->profile()));
        $assignment->render($out);
        self::assertSame('a = (1, 2)', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
        self::assertSame($column, $write->onDuplicate[0]->column);
        self::assertSame($value, $write->onDuplicate[0]->value);
        self::assertSame('Operand should contain 1 column(s), not 2.', $write->problem()->message());
    }
}
