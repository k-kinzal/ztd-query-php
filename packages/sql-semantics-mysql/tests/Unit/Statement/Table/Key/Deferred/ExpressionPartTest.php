<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key\Deferred;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart as DeferredExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(DeferredExpressionPart::class)]
#[Medium]
final class ExpressionPartTest extends TestCase
{
    public function testDeriveKeyPartPreservesTheDeclarationScope(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t(a INT, KEY (((a,2))))');
        self::assertInstanceOf(CreateTable::class, $create->statement);
        $index = $create->statement->elements[1];
        self::assertInstanceOf(IndexDefinition::class, $index);
        $part = $index->parts[0];
        self::assertInstanceOf(DeferredExpressionPart::class, $part);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand::class, $part->expression);
        $fields = (new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes())->parts($part->expression);
        self::assertNotNull($fields);
        self::assertInstanceOf(\SqlSemantics\Statement\Operand::class, $fields[0]);
        self::assertInstanceOf(ResolvedColumn::class, $create->facts->scalar($fields[0])->resolution);
    }

    public function testRenderKeepsTheParentAndTheCompleteOperand(): void
    {
        self::assertSame('CREATE TABLE IF NOT EXISTS t (a INT, PRIMARY KEY ((CAST(1 AT TIME ZONE \'text\' AS DATETIME))))', (new Semantics(Dialect::MySql))->analyze("CREATE TABLE IF NOT EXISTS t(a INT, PRIMARY KEY ((CAST(1 AT TIME ZONE 'text' AS DATETIME))))")->toString());
    }
}
