<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Rule\RuleEvent;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule\CreateEmptyRuleStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Trigger\Rules;

#[CoversClass(Rules::class)]
#[Medium]
final class RulesTest extends TestCase
{
    #[TestWith(['CREATE RULE "r" AS ON INSERT TO "public"."t" DO ALSO NOTHING'])]
    #[TestWith(['CREATE OR REPLACE RULE "_RETURN" AS ON SELECT TO "public"."t" DO INSTEAD SELECT 1 AS "a"'])]
    #[TestWith(['CREATE RULE "r" AS ON UPDATE TO "public"."t" DO ALSO(NOTIFY "x"; NOTIFY "y", \'z\')'])]
    public function testWriteIsAFixedPoint(string $sql): void
    {
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind($sql)));
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        self::assertNull(Rules::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('DROP RULE r ON t')));
    }

    public function testHeadWritesAlsoExplicitly(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON DELETE TO t DO NOTHING');
        self::assertInstanceOf(CreateEmptyRuleStatement::class, $statement);
        self::assertCount(5, Rules::head(false, 'r', RuleEvent::Delete, $statement->table, null, false));
    }
}
