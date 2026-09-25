<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Trigger\Rules;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule\CreateCommandRuleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule\CreateEmptyRuleStatement;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Statement\UpdateStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Rules::class)]
#[Medium]
final class RulesTest extends TestCase
{
    public function testBindReadsTheConditionAndActions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT); CREATE TABLE log(a INT)'));
        $statement = $binder->bind('CREATE OR REPLACE RULE r AS ON UPDATE TO t WHERE NEW.a > 1 DO INSTEAD (INSERT INTO log VALUES (OLD.a); ; UPDATE log SET a = NEW.a)');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $statement);
        self::assertTrue($statement->instead);
        self::assertTrue($statement->orReplace);
        self::assertInstanceOf(InsertStatement::class, $statement->actions[0]);
        self::assertInstanceOf(UpdateStatement::class, $statement->actions[1]);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['DO NOTHING'])]
    #[TestWith(['DO ALSO ( ; ; )'])]
    public function testBindTreatsEmptyActionsAsNothing(string $body): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON DELETE TO t ' . $body);
        self::assertInstanceOf(CreateEmptyRuleStatement::class, $statement);
        self::assertFalse($statement->instead);
    }

    #[TestWith(['CREATE RULE r AS ON SELECT TO t DO INSTEAD NOTHING'])]
    #[TestWith(['CREATE RULE r AS ON SELECT TO t DO INSTEAD SELECT 1'])]
    #[TestWith(['CREATE OR REPLACE RULE "_RETURN" AS ON SELECT TO t DO ALSO SELECT 1'])]
    #[TestWith(['CREATE OR REPLACE RULE "_RETURN" AS ON SELECT TO t DO INSTEAD DELETE FROM t'])]
    #[TestWith(['CREATE RULE r AS ON INSERT TO t WHERE NEW.a > 0 DO NOTIFY ch'])]
    #[TestWith(['CREATE RULE r AS ON INSERT TO t DO ALSO INSERT INTO t VALUES (1) RETURNING a'])]
    public function testBindDiagnosesARuleThePostgreSqlServerRejects(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RewriteRule->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind($sql);
    }

    public function testActionReadsTheRowImagesOfTheEvent(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t DO INSTEAD SELECT NEW.a');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $statement);
        self::assertInstanceOf(BoundQuery::class, $statement->actions[0]);
        self::assertSame('CREATE RULE "r" AS ON INSERT TO "public"."t" DO INSTEAD SELECT "new"."a" AS "a"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testActionCannotReadAnImageTheEventLacks(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t DO ALSO SELECT OLD.a', strict: false);
        self::assertInstanceOf(CreateCommandRuleStatement::class, $statement);
        self::assertNotSame([], $statement->diagnostics);
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindReadsEachLowercaseRule(): array
    {
        return [
            [Dialect::PostgreSql, null, 'create or replace rule r as on insert to t where new.a > 0 do instead nothing', [CreateEmptyRuleStatement::class, 'CREATE OR REPLACE RULE "r" AS ON INSERT TO "public"."t" WHERE ("new"."a" > 0) DO INSTEAD NOTHING']],
            [Dialect::PostgreSql, null, 'create rule r as on update to t do also nothing', [CreateEmptyRuleStatement::class, 'CREATE RULE "r" AS ON UPDATE TO "public"."t" DO ALSO NOTHING']],
            [Dialect::PostgreSql, null, 'create rule r as on delete to t do also notify ch', [CreateCommandRuleStatement::class, 'CREATE RULE "r" AS ON DELETE TO "public"."t" DO ALSO NOTIFY "ch"']],
            [Dialect::PostgreSql, null, 'create or replace rule r as on insert to t do instead (insert into u values (new.a); select 1)', [CreateCommandRuleStatement::class, 'CREATE OR REPLACE RULE "r" AS ON INSERT TO "public"."t" DO INSTEAD(INSERT INTO "public"."u" VALUES ("new"."a"); SELECT 1)']],
        ];
    }

    #[DataProvider('providerBindReadsEachLowercaseRule')]
    public function testBindReadsEachLowercaseRule(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INT); CREATE TABLE u(a INT)')))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
