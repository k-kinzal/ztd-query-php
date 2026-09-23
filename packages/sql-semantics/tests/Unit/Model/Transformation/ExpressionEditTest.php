<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transformation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Transformation\ExpressionEdit;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExpressionEdit::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ExpressionEditTest extends TestCase
{
    public function testRebuildPreservesUnchangedReferenceIdentities(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(x INTEGER, y INTEGER)')))->bind('SELECT x, y FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $replacement = Expression::literal(5, Dialect::PostgreSql);
        $copy = ExpressionEdit::rebuild($query, $query->outputs[0]->expression, $replacement);
        self::assertSame($replacement, $copy->outputs[0]->expression);
        self::assertSame($query->outputs[1]->expression, $copy->outputs[1]->expression);
        self::assertSame($query->from, $copy->from);
    }

    public function testReplaceSupportsIndependentSuccessiveChanges(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(x INTEGER, y INTEGER)')))->bind('SELECT x, y FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $x = $query->outputs[0]->expression;
        $y = $query->outputs[1]->expression;
        $copy = ExpressionEdit::replace(ExpressionEdit::replace($query, $x, Expression::literal(1, Dialect::PostgreSql)), $y, Expression::literal(2, Dialect::PostgreSql));
        self::assertSame('1', $copy->outputs[0]->expression->spelling());
        self::assertSame('2', $copy->outputs[1]->expression->spelling());
        self::assertSame($x, $query->outputs[0]->expression);
        self::assertSame($y, $query->outputs[1]->expression);
    }

    public function testReplaceRejectsAnExpressionOutsideTheStatement(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        ExpressionEdit::replace($query, Expression::literal(1, Dialect::PostgreSql), Expression::literal(2, Dialect::PostgreSql));
    }
}
