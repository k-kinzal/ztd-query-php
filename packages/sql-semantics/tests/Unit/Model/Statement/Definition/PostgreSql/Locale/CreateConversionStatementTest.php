<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Locale;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Conversion\ServerEncoding;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Locale\CreateConversionStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateConversionStatement::class)]
#[Medium]
final class CreateConversionStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE CONVERSION s.c FOR 'windows-1252' TO \$\$utf-8\$\$ FROM s.f");
        self::assertInstanceOf(CreateConversionStatement::class, $statement);
        self::assertSame(ServerEncoding::Win1252, $statement->sourceEncoding);
        self::assertSame(ServerEncoding::Utf8, $statement->targetEncoding);
        self::assertSame(['s', 'f'], $statement->function->parts);
        self::assertFalse($statement->isDefault);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertSame("CREATE CONVERSION \"s\".\"c\" FOR 'WIN1252' TO 'UTF8' FROM \"s\".\"f\"", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAConversionToSqlAscii(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE CONVERSION c FOR 'UTF8' TO 'LATIN1' FROM f");
        self::assertInstanceOf(CreateConversionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTargetEncoding(ServerEncoding::SqlAscii);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE CONVERSION c FOR 'UTF8' TO 'LATIN1' FROM f");
        self::assertInstanceOf(CreateConversionStatement::class, $statement);
        self::assertSame("CREATE CONVERSION \"c\" FOR 'UTF8' TO 'LATIN1' FROM \"f\"", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE CONVERSION c FOR 'UTF8' TO 'LATIN1' FROM f");
        self::assertInstanceOf(CreateConversionStatement::class, $statement);
        self::assertSame(['d'], $statement->withName(new QualifiedName(['d']))->name->parts);
        self::assertSame(['c'], $statement->name->parts);
    }

    public function testWithSourceEncodingReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE CONVERSION c FOR 'UTF8' TO 'LATIN1' FROM f");
        self::assertInstanceOf(CreateConversionStatement::class, $statement);
        self::assertSame("CREATE CONVERSION \"c\" FOR 'EUC_JP' TO 'LATIN1' FROM \"f\"", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withSourceEncoding(ServerEncoding::EucJp)));
    }

    public function testWithTargetEncodingReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE CONVERSION c FOR 'UTF8' TO 'LATIN1' FROM f");
        self::assertInstanceOf(CreateConversionStatement::class, $statement);
        self::assertSame(ServerEncoding::Big5, $statement->withTargetEncoding(ServerEncoding::Big5)->targetEncoding);
    }

    public function testWithFunctionReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE CONVERSION c FOR 'UTF8' TO 'LATIN1' FROM f");
        self::assertInstanceOf(CreateConversionStatement::class, $statement);
        self::assertSame(['s', 'g'], $statement->withFunction(new QualifiedName(['s', 'g']))->function->parts);
    }

    public function testWithIsDefaultReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE CONVERSION c FOR 'UTF8' TO 'LATIN1' FROM f");
        self::assertInstanceOf(CreateConversionStatement::class, $statement);
        self::assertSame("CREATE DEFAULT CONVERSION \"c\" FOR 'UTF8' TO 'LATIN1' FROM \"f\"", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withIsDefault(true)));
    }
}
