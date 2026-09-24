<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Decision;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\MergeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Decision\ActionKind;
use SqlSemantics\Model\Write\Decision\MatchKind;
use SqlSemantics\Model\Write\Decision\MergeInsertDefaults;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MergeInsertDefaults::class)]
#[Medium]
final class MergeInsertDefaultsTest extends TestCase
{
    public function testBindsADefaultRowInsertion(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('MERGE INTO t USING s ON t.id=s.id WHEN NOT MATCHED AND s.id>0 THEN INSERT DEFAULT VALUES');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $action = $statement->merge->actions[0];
        self::assertInstanceOf(MergeInsertDefaults::class, $action);
        self::assertSame(ActionKind::Insert, $action->action);
        self::assertSame(MatchKind::MissingTarget, $action->match);
        self::assertSame('>', $action->condition?->spelling());
        self::assertSame([], $action->insertion->columns);
        self::assertSame(['id'], array_column($action->insertion->omittedColumns, 'name'));
        $expected = 'MERGE INTO "public"."t" USING "public"."s" ON ("t"."id" = "s"."id") WHEN NOT MATCHED AND ("s"."id" > 0) THEN INSERT DEFAULT VALUES';
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testRejectsAnExistingTarget(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)');
        $statement = (new Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN NOT MATCHED THEN INSERT DEFAULT VALUES');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $action = $statement->merge->actions[0];
        self::assertInstanceOf(MergeInsertDefaults::class, $action);
        $this->expectException(InvalidStructure::class);
        new MergeInsertDefaults(MatchKind::Matched, null, new Node('action', 0, []), $action->insertion);
    }
}
