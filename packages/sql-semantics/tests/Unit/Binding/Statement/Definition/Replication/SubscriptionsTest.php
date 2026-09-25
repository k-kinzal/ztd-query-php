<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Replication\Subscriptions::class)]
#[Medium]
final class SubscriptionsTest extends TestCase
{
    #[TestWith(["CREATE SUBSCRIPTION s CONNECTION 'c' PUBLICATION p", Statement\CreateSubscriptionStatement::class])]
    #[TestWith(['DROP SUBSCRIPTION IF EXISTS s RESTRICT', Statement\DropSubscriptionStatement::class])]
    #[TestWith(['ALTER SUBSCRIPTION s SET (slot_name = x)', Statement\AlterSubscriptionOptionsStatement::class])]
    public function testBindSeparatesEachForm(string $sql, string $class): void
    {
        $binder = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()));
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(["CREATE SUBSCRIPTION s CONNECTION 'c' PUBLICATION p WITH (slot_name = NONE)"])]
    #[TestWith(["CREATE SUBSCRIPTION s CONNECTION 'c' PUBLICATION p WITH (connect = false, enabled)"])]
    #[TestWith(["CREATE SUBSCRIPTION s CONNECTION 'c' PUBLICATION p, p"])]
    public function testBindDiagnosesARejectedCombination(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionRequirement->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    #[TestWith(["ALTER SUBSCRIPTION s CONNECTION 'c'", Statement\AlterSubscriptionConnectionStatement::class])]
    #[TestWith(['ALTER SUBSCRIPTION s DISABLE', Statement\AlterSubscriptionEnabledStatement::class])]
    #[TestWith(['ALTER SUBSCRIPTION s REFRESH PUBLICATION WITH (copy_data)', Statement\RefreshSubscriptionStatement::class])]
    #[TestWith(['ALTER SUBSCRIPTION s SKIP (lsn = NONE)', Statement\SkipSubscriptionTransactionStatement::class])]
    #[TestWith(['ALTER SUBSCRIPTION s SET PUBLICATION p WITH (refresh = false)', Statement\AlterSubscriptionPublicationsStatement::class])]
    public function testAlterSeparatesEachForm(string $sql, string $class): void
    {
        self::assertSame($class, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)::class);
    }

    public function testConnectionDecodesTheString(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER SUBSCRIPTION s CONNECTION E'host=a\\tb'");
        self::assertInstanceOf(Statement\AlterSubscriptionConnectionStatement::class, $statement);
        self::assertSame("host=a\tb", $statement->connection);
    }

    public function testPublicationsFoldUnquotedNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SUBSCRIPTION s DROP PUBLICATION Pub, "Pub"');
        self::assertInstanceOf(Statement\AlterSubscriptionPublicationsStatement::class, $statement);
        self::assertSame(['pub', 'Pub'], $statement->publications);
    }

    #[TestWith(['create subscription s connection \'dbname=x\' publication p, q', Statement\CreateSubscriptionStatement::class, 'CREATE SUBSCRIPTION "s" CONNECTION \'dbname=x\' PUBLICATION "p", "q"'])]
    #[TestWith(['drop subscription if exists s cascade', Statement\DropSubscriptionStatement::class, 'DROP SUBSCRIPTION IF EXISTS "s" CASCADE'])]
    #[TestWith(['DROP SUBSCRIPTION s', Statement\DropSubscriptionStatement::class, 'DROP SUBSCRIPTION "s"'])]
    #[TestWith(['drop subscription s restrict', Statement\DropSubscriptionStatement::class, 'DROP SUBSCRIPTION "s" RESTRICT'])]
    #[TestWith(['alter subscription s enable', Statement\AlterSubscriptionEnabledStatement::class, 'ALTER SUBSCRIPTION "s" ENABLE'])]
    #[TestWith(['alter subscription s disable', Statement\AlterSubscriptionEnabledStatement::class, 'ALTER SUBSCRIPTION "s" DISABLE'])]
    #[TestWith(['alter subscription s skip (lsn = \'0/12345\')', Statement\SkipSubscriptionTransactionStatement::class, 'ALTER SUBSCRIPTION "s" SKIP(lsn = \'0/12345\')'])]
    #[TestWith(['alter subscription s connection \'x\'', Statement\AlterSubscriptionConnectionStatement::class, 'ALTER SUBSCRIPTION "s" CONNECTION \'x\''])]
    #[TestWith(['alter subscription s set publication p', Statement\AlterSubscriptionPublicationsStatement::class, 'ALTER SUBSCRIPTION "s" SET PUBLICATION "p"'])]
    #[TestWith(['alter subscription s add publication p', Statement\AlterSubscriptionPublicationsStatement::class, 'ALTER SUBSCRIPTION "s" ADD PUBLICATION "p"'])]
    #[TestWith(['alter subscription s refresh publication', Statement\RefreshSubscriptionStatement::class, 'ALTER SUBSCRIPTION "s" REFRESH PUBLICATION'])]
    #[TestWith(['alter subscription s set (slot_name = NONE)', Statement\AlterSubscriptionOptionsStatement::class, 'ALTER SUBSCRIPTION "s" SET (slot_name = NONE)'])]
    public function testBindSpellsEverySubscriptionForm(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
