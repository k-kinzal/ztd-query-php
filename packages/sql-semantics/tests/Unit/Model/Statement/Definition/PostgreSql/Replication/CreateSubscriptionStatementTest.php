<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Subscription as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Statement\CreateSubscriptionStatement::class)]
#[Medium]
final class CreateSubscriptionStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE SUBSCRIPTION s CONNECTION 'host=a' PUBLICATION p");
        self::assertInstanceOf(Statement\CreateSubscriptionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Create, $copy->kind);
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE SUBSCRIPTION s CONNECTION 'host=a' PUBLICATION p");
        self::assertInstanceOf(Statement\CreateSubscriptionStatement::class, $statement);
        self::assertSame('q', $statement->withName('q')->name);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithConnectionKeepsTheTextUnparsed(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE SUBSCRIPTION s CONNECTION 'host=a' PUBLICATION p");
        self::assertInstanceOf(Statement\CreateSubscriptionStatement::class, $statement);
        self::assertSame('CREATE SUBSCRIPTION "s" CONNECTION \'x=\'\'y\'\'\' PUBLICATION "p"', $statement->withConnection("x='y'")->toString());
    }

    public function testWithPublicationsRejectsARepeatedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE SUBSCRIPTION s CONNECTION 'host=a' PUBLICATION p");
        self::assertInstanceOf(Statement\CreateSubscriptionStatement::class, $statement);
        self::assertSame(['a', 'b'], $statement->withPublications(['a', 'b'])->publications);
        $this->expectException(InvalidStructure::class);
        $statement->withPublications(['a', 'a']);
    }

    public function testWithOptionsRejectsAnAlterationOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE SUBSCRIPTION s CONNECTION 'host=a' PUBLICATION p");
        self::assertInstanceOf(Statement\CreateSubscriptionStatement::class, $statement);
        self::assertSame(false, $statement->withOptions(new Operand\SubscriptionOptions(enabled: false))->options->enabled);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions(new Operand\SubscriptionOptions(refresh: false));
    }
}
