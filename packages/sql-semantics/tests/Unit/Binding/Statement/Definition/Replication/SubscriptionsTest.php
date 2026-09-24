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
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
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
}
