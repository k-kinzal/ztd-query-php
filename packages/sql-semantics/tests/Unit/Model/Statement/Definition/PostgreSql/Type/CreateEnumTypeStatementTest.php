<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type\CreateEnumTypeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateEnumTypeStatement::class)]
#[Medium]
final class CreateEnumTypeStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE TYPE mood AS ENUM ('sad', E'it\\'s', \$\$ok\$\$)");
        self::assertInstanceOf(CreateEnumTypeStatement::class, $statement);
        self::assertSame(['sad', "it's", 'ok'], $statement->labels);
        self::assertSame("CREATE TYPE \"mood\" AS ENUM('sad', 'it''s', 'ok')", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE mood AS ENUM ()');
        self::assertInstanceOf(CreateEnumTypeStatement::class, $statement);
        self::assertSame('CREATE TYPE "mood" AS ENUM()', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE TYPE mood AS ENUM ('a')");
        self::assertInstanceOf(CreateEnumTypeStatement::class, $statement);
        self::assertSame("CREATE TYPE \"feeling\" AS ENUM('a')", $statement->withName(new QualifiedName(['feeling']))->toString());
    }

    public function testWithLabelsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE TYPE mood AS ENUM ('a')");
        self::assertInstanceOf(CreateEnumTypeStatement::class, $statement);
        self::assertSame(['b', 'c'], $statement->withLabels(['b', 'c'])->labels);
        self::assertSame(['a'], $statement->labels);
    }

    public function testRejectsALabelLongerThanSixtyThreeBytes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE TYPE mood AS ENUM ('a')");
        self::assertInstanceOf(CreateEnumTypeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withLabels([str_repeat('x', 64)]);
    }
}
