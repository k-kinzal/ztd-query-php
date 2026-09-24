<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Tablespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace\CreateTablespaceStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateTablespaceStatement::class)]
#[Medium]
final class CreateTablespaceStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRequestedTablespace(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE TABLESPACE fast LOCATION '/ssd'");
        self::assertInstanceOf(CreateTablespaceStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNull($copy->owner);
        self::assertSame(StatementKind::Create, $copy->kind);
    }

    public function testWithNameReplacesTheTablespaceName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE TABLESPACE fast LOCATION '/ssd'");
        self::assertInstanceOf(CreateTablespaceStatement::class, $statement);
        self::assertSame("CREATE TABLESPACE \"slow\" LOCATION '/ssd'", $statement->withName('slow')->toString());
    }

    public function testWithOwnerAddsASessionRole(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE TABLESPACE fast OWNER alice LOCATION '/ssd'");
        self::assertInstanceOf(CreateTablespaceStatement::class, $statement);
        self::assertSame("CREATE TABLESPACE \"fast\" OWNER SESSION_USER LOCATION '/ssd'", $statement->withOwner(SessionRole::SessionUser)->toString());
        self::assertNull($statement->withOwner(null)->owner);
    }

    public function testWithLocationRequiresATextConstant(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE TABLESPACE fast LOCATION '/ssd'");
        self::assertInstanceOf(CreateTablespaceStatement::class, $statement);
        $other = $binder->bind("CREATE TABLESPACE fast LOCATION '/hdd' WITH (seq_page_cost = 4)");
        self::assertInstanceOf(CreateTablespaceStatement::class, $other);
        self::assertSame("'/hdd'", $statement->withLocation($other->location)->location->text);
        $number = $other->parameters[0]->value;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        $statement->withLocation($number);
    }

    public function testWithParametersReplacesTheOverrides(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE TABLESPACE fast LOCATION '/ssd' WITH (seq_page_cost = 4)");
        self::assertInstanceOf(CreateTablespaceStatement::class, $statement);
        self::assertSame("CREATE TABLESPACE \"fast\" LOCATION '/ssd'", $statement->withParameters([])->toString());
    }
}
