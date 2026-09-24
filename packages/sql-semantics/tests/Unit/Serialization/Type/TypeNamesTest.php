<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Type\TypeNames;
use SqlSemantics\Serialization\TypeDeclaration;

#[CoversClass(TypeNames::class)]
#[Medium]
final class TypeNamesTest extends TestCase
{
    public function testWriteSqliteQuotesDeclaredNamesWithTheirParameters(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a VARCHAR(20), b DOUBLE PRECISION, c NUMERIC(10, 2))');
        $sized = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\SqliteDeclaration::class, $sized);
        self::assertSame('"varchar"(20)', TypeNames::writeSqlite($sized)->toString());
        $multiword = $schema->tables[0]->columns[1]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\SqliteDeclaration::class, $multiword);
        self::assertSame('"double precision"', TypeNames::writeSqlite($multiword)->toString());
        self::assertSame('"numeric"(10, 2)', TypeDeclaration::write($schema->tables[0]->columns[2]->type)->toString());
    }

    public function testNamedWritesQualifiedTypesAndModifiers(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a app.money(currency, 12), b app.money)');
        $modified = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\NamedIdentity::class, $modified);
        self::assertSame('"app"."money"("currency", 12)', TypeNames::named($modified, Dialect::PostgreSql)->toString());
        $bare = $schema->tables[0]->columns[1]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\NamedIdentity::class, $bare);
        self::assertSame('"app"."money"', TypeNames::named($bare, Dialect::PostgreSql)->toString());
    }

    public function testArrayWritesEveryDimension(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a integer[][3], b text[])');
        $nested = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\ArrayStorage::class, $nested);
        self::assertSame('integer [] [3]', TypeNames::array($nested)->toString());
        $single = $schema->tables[0]->columns[1]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\ArrayStorage::class, $single);
        self::assertSame('text []', TypeNames::array($single)->toString());
    }

    public function testLabelsWritesEnumAndSetValuesWithEncoding(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build("CREATE TABLE t(a ENUM('a','b') CHARACTER SET utf8mb4 BINARY, b SET('x','y'))");
        $enumeration = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Enumeration::class, $enumeration);
        self::assertSame("ENUM('a', 'b') CHARACTER SET `utf8mb4` BINARY", TypeNames::labels($enumeration, Dialect::MySql)->toString());
        $set = $schema->tables[0]->columns[1]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\LabelSet::class, $set);
        self::assertSame("SET ('x', 'y')", TypeNames::labels($set, Dialect::MySql)->toString());
    }
}
