<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\WindowCall;
use SqlSemantics\Model\Window\CurrentRow;
use SqlSemantics\Model\Window\NamedWindow;
use SqlSemantics\Model\Window\Offset;
use SqlSemantics\Model\Window\Unbounded;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\Windows;

#[CoversClass(Windows::class)]
#[Medium]
final class WindowsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'SELECT SUM(n) OVER (PARTITION BY id ORDER BY n ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE CURRENT ROW) FROM t', '(PARTITION BY "id" ORDER BY "n" ASC ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE CURRENT ROW)'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT SUM(n) OVER (ORDER BY n GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) FROM t', '(ORDER BY "n" ASC GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES)'])]
    #[TestWith([Dialect::MySql, 'SELECT SUM(n) OVER (ORDER BY n RANGE BETWEEN 1 PRECEDING AND 1 FOLLOWING) FROM t', '(ORDER BY `n` ASC RANGE BETWEEN 1 PRECEDING AND 1 FOLLOWING)'])]
    #[TestWith([Dialect::MySql, 'SELECT SUM(n) OVER (w ORDER BY n) FROM t WINDOW w AS (PARTITION BY id)', '(`w` ORDER BY `n` ASC)'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT SUM(n) OVER () FROM t', '()'])]
    public function testWriteSerializesPartitioningOrderingFrameAndExclusion(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(WindowSpecification::class, $call->window);
        self::assertSame($expected, Windows::write($call->window, $dialect)->toString());
        self::assertStringContainsString(' OVER ' . $expected, $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWriteQuotesANamedWindowReference(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('SELECT SUM(n) OVER w FROM t WINDOW w AS (PARTITION BY id)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(NamedWindow::class, $call->window);
        self::assertSame('"w"', Windows::write($call->window, Dialect::PostgreSql)->toString());
        self::assertSame('SELECT "sum"("n") OVER "w" FROM "public"."t" WINDOW "w" AS (PARTITION BY "id")', $statement->toString());
    }

    public function testBoundaryDistinguishesOffsetsFromTheUnboundedAndCurrentRowForms(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)')))->bind('SELECT SUM(n) OVER (ORDER BY n ROWS BETWEEN 2 PRECEDING AND CURRENT ROW), SUM(n) OVER (ORDER BY n ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $first = $statement->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $first);
        self::assertInstanceOf(WindowSpecification::class, $first->window);
        self::assertNotNull($first->window->frame);
        self::assertInstanceOf(Offset::class, $first->window->frame->start);
        self::assertSame('2 PRECEDING', Windows::boundary($first->window->frame->start)->toString());
        self::assertInstanceOf(CurrentRow::class, $first->window->frame->end);
        self::assertSame('CURRENT ROW', Windows::boundary($first->window->frame->end)->toString());
        $second = $statement->outputs[1]->expression;
        self::assertInstanceOf(WindowCall::class, $second);
        self::assertInstanceOf(WindowSpecification::class, $second->window);
        self::assertNotNull($second->window->frame);
        self::assertInstanceOf(Unbounded::class, $second->window->frame->end);
        self::assertSame('UNBOUNDED FOLLOWING', Windows::boundary($second->window->frame->end)->toString());
    }
}
