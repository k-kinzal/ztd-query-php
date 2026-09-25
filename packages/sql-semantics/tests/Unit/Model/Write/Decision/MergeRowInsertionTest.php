<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Decision;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\MergeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Decision\ActionKind;
use SqlSemantics\Model\Write\Decision\MatchKind;
use SqlSemantics\Model\Write\Decision\MergeRowInsertion;
use SqlSemantics\Model\Write\InputRow;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MergeRowInsertion::class)]
#[Medium]
final class MergeRowInsertionTest extends TestCase
{
    public function testBindsARowInsertionWithItsDestinationsAndItems(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)', 'CREATE TABLE s(id INTEGER, n INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('MERGE INTO t USING s ON t.id=s.id WHEN NOT MATCHED THEN INSERT (n, id) VALUES (s.n, DEFAULT)');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $action = $statement->merge->actions[0];
        self::assertInstanceOf(MergeRowInsertion::class, $action);
        self::assertSame(ActionKind::Insert, $action->action);
        self::assertSame(MatchKind::MissingTarget, $action->match);
        self::assertCount(2, $action->insertion->columns);
        self::assertTrue($action->insertion->explicitColumns);
        self::assertCount(2, $action->row->items);
        self::assertSame(Dialect::PostgreSql, $action->row->dialect);
        $expected = 'MERGE INTO "public"."t" USING "public"."s" ON ("t"."id" = "s"."id") WHEN NOT MATCHED THEN INSERT("n", "id") VALUES ("s"."n", DEFAULT)';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testRejectsAnExistingTarget(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)');
        $statement = (new Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN NOT MATCHED THEN INSERT VALUES (s.id)');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $action = $statement->merge->actions[0];
        self::assertInstanceOf(MergeRowInsertion::class, $action);
        $this->expectException(InvalidStructure::class);
        new MergeRowInsertion(MatchKind::Matched, null, new Node('action', 0, []), $action->insertion, $action->row);
    }

    public function testRejectsARowWiderThanTheDestinations(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)');
        $statement = (new Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN NOT MATCHED THEN INSERT (id) VALUES (s.id)');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $action = $statement->merge->actions[0];
        self::assertInstanceOf(MergeRowInsertion::class, $action);
        $row = new InputRow(Dialect::PostgreSql, [Expression::literal(1, Dialect::PostgreSql), Expression::literal(2, Dialect::PostgreSql)]);
        $this->expectException(InvalidStructure::class);
        new MergeRowInsertion(MatchKind::MissingTarget, null, new Node('action', 0, []), $action->insertion, $row);
    }
}
