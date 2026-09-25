<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Trigger\ChangeReactions;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChangeReactions::class)]
#[Medium]
final class ChangeReactionsTest extends TestCase
{
    #[TestWith(['CREATE TRIGGER x AFTER INSERT ON t EXECUTE FUNCTION f()', \SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateTriggerStatement::class])]
    #[TestWith(['CREATE EVENT TRIGGER x ON sql_drop EXECUTE FUNCTION f()', \SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateEventTriggerStatement::class])]
    #[TestWith(['CREATE POLICY p ON t', \SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy\CreatePolicyStatement::class])]
    #[TestWith(['ALTER POLICY p ON t', \SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Policy\AlterPolicyStatement::class])]
    #[TestWith(['CREATE RULE r AS ON INSERT TO t DO NOTHING', \SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule\CreateEmptyRuleStatement::class])]
    #[TestWith(['CREATE PUBLICATION p FOR ALL TABLES', \SqlSemantics\Model\Statement\Definition\PostgreSql\Replication\CreateAllTablesPublicationStatement::class])]
    #[TestWith(['DROP SUBSCRIPTION s', \SqlSemantics\Model\Statement\Definition\PostgreSql\Replication\DropSubscriptionStatement::class])]
    public function testBindRoutesEachStatementToItsForm(string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind($sql);
        self::assertSame($class, $statement::class);
    }

    public function testBindLeavesOtherStatementsAlone(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        self::assertSame(\SqlSemantics\Model\Statement\Definition\DropTableTriggerStatement::class, $binder->bind('DROP TRIGGER x ON t')::class);
    }

    #[TestWith(['create publication p for table t', 'CREATE PUBLICATION "p" FOR TABLE "public"."t"'])]
    #[TestWith(['alter subscription s disable', 'ALTER SUBSCRIPTION "s" DISABLE'])]
    #[TestWith(['create event trigger e on ddl_command_start execute function f()', 'CREATE EVENT TRIGGER "e" ON "ddl_command_start" EXECUTE FUNCTION "f"()'])]
    public function testBindRoutesPublicationsSubscriptionsAndEventTriggers(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a int)')))->bind($sql)));
    }
}
