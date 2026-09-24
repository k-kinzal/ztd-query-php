<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Expressions;

#[CoversClass(Expressions::class)]
#[Medium]
final class ExpressionsTest extends TestCase
{
    #[TestWith(['1', '1'])]
    #[TestWith(['CURRENT_TIMESTAMP(3)', 'CURRENT_TIMESTAMP(3)'])]
    #[TestWith(['ROW(1, 2)', 'ROW(1, 2)'])]
    #[TestWith(['t.id', '"t"."id"'])]
    #[TestWith(['$1', '$1'])]
    #[TestWith(['n COLLATE "C"', '("n" COLLATE "C")'])]
    #[TestWith(['id + 1', '("id" + 1)'])]
    #[TestWith(['-id', '(- "id")'])]
    #[TestWith(['CAST(id AS text)', 'CAST("id" AS text)'])]
    #[TestWith(['COALESCE(id, 1)', 'COALESCE("id", 1)'])]
    #[TestWith(['id BETWEEN 1 AND 2', '("id" BETWEEN 1 AND 2)'])]
    #[TestWith(['CASE WHEN id > 1 THEN 2 END', 'CASE WHEN ("id" > 1) THEN 2 END'])]
    #[TestWith(['(SELECT 1)', '(SELECT 1)'])]
    #[TestWith(['EXISTS (SELECT 1)', 'EXISTS(SELECT 1)'])]
    #[TestWith(['id IN (SELECT id FROM s)', '("id" IN (SELECT "id" AS "id" FROM "public"."s"))'])]
    #[TestWith(['lower(n)', '"lower"("n")'])]
    #[TestWith(['count(*)', '"count"(*)'])]
    #[TestWith(['sum(n) OVER ()', '"sum"("n") OVER ()'])]
    #[TestWith(['EXTRACT(YEAR FROM CURRENT_DATE)', 'EXTRACT(YEAR FROM CURRENT_DATE)'])]
    #[TestWith(['POSITION(\'a\' IN n)', 'POSITION(\'a\' IN "n")'])]
    public function testWriteRoutesEveryExpressionCategoryToItsSerializer(string $expression, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n TEXT)', 'CREATE TABLE s(id INT, n TEXT)'));
        $statement = $binder->bind('SELECT ' . $expression . ' FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, Expressions::write($statement->outputs[0]->expression)->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(BoundSelect::class, $rebound);
        self::assertSame($statement->outputs[0]->expression::class, $rebound->outputs[0]->expression::class);
        self::assertSame($expected, Expressions::write($rebound->outputs[0]->expression)->toString());
    }

    public function testWriteSerializesTriggerControlFlow(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind("CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT RAISE(ABORT, 'no'); END");
        self::assertSame("CREATE TRIGGER \"tr\" AFTER INSERT ON \"main\".\"t\" FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'no'); END", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith(['ARRAY(SELECT 1)', 'ARRAY(SELECT 1)'])]
    #[TestWith(['1 = ANY (ARRAY[1])', '(1 = ANY (ARRAY[1]))'])]
    #[TestWith(['coalesce(1, 2)', 'COALESCE(1, 2)'])]
    public function testCompositeWritesExpressionsThatContainExpressions(string $expression, string $expected): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT ' . $expression);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame($expected, Expressions::composite($query->outputs[0]->expression)->toString());
    }
}
