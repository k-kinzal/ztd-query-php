<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(TypeDeclaration::class)]
#[Medium]
final class TypeDeclarationTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'INT', 'integer'])]
    #[TestWith([Dialect::PostgreSql, 'INT[]', 'integer []'])]
    #[TestWith([Dialect::PostgreSql, 'TEXT[3][]', 'text [3] []'])]
    #[TestWith([Dialect::PostgreSql, 'NUMERIC(10, 2)', 'numeric(10, 2)'])]
    #[TestWith([Dialect::PostgreSql, 'VARCHAR(5)', 'varchar(5)'])]
    #[TestWith([Dialect::PostgreSql, 'TIMESTAMP(3) WITH TIME ZONE', 'timestamp(3) WITH TIME ZONE'])]
    #[TestWith([Dialect::PostgreSql, 'INTERVAL DAY TO SECOND(2)', 'INTERVAL DAY TO SECOND(2)'])]
    #[TestWith([Dialect::PostgreSql, 'app.money(3)', '"app"."money"(3)'])]
    #[TestWith([Dialect::PostgreSql, 'BOOLEAN', 'boolean'])]
    #[TestWith([Dialect::MySql, 'INT(11) UNSIGNED', 'integer(11) UNSIGNED'])]
    #[TestWith([Dialect::MySql, 'DECIMAL(10, 2) UNSIGNED', 'numeric(10, 2) UNSIGNED'])]
    #[TestWith([Dialect::MySql, 'NATIONAL CHAR(3)', 'NATIONAL char(3)'])]
    #[TestWith([Dialect::MySql, "ENUM('a', 'b') CHARACTER SET latin1 BINARY", "ENUM('a', 'b') CHARACTER SET `latin1` BINARY"])]
    #[TestWith([Dialect::MySql, "SET('x', 'y')", "SET ('x', 'y')"])]
    #[TestWith([Dialect::MySql, 'DATETIME(6)', 'datetime(6)'])]
    #[TestWith([Dialect::Sqlite, '"my type"(3, 4)', '"my type"(3, 4)'])]
    #[TestWith([Dialect::Sqlite, 'INTEGER', '"integer"'])]
    public function testWriteDispatchesEachIdentityToItsOwnSerializer(Dialect $dialect, string $declaration, string $expected): void
    {
        $builder = new SchemaBuilder($dialect);
        $type = $builder->build('CREATE TABLE t(x ' . $declaration . ')')->tables[0]->columns[0]->type;
        self::assertSame($expected, TypeDeclaration::write($type)->toString());
        $rebound = $builder->build('CREATE TABLE t(x ' . $expected . ')')->tables[0]->columns[0]->type;
        self::assertSame($type->identity::class, $rebound->identity::class);
        self::assertSame($type->name, $rebound->name);
        self::assertSame($expected, TypeDeclaration::write($rebound)->toString());
    }

    public function testWriteWritesAnUntypedSqliteColumnAsNothing(): void
    {
        $type = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(x)')->tables[0]->columns[0]->type;
        self::assertSame('', TypeDeclaration::write($type)->toString());
    }

    #[TestWith([BuiltinIdentity::Unknown])]
    #[TestWith([BuiltinIdentity::Dynamic])]
    #[TestWith([BuiltinIdentity::Never])]
    #[TestWith([BuiltinIdentity::Record])]
    public function testWriteRejectsInferredResultCategories(BuiltinIdentity $identity): void
    {
        $this->expectException(InvalidStructure::class);
        TypeDeclaration::write(new TypeDescriptor(Dialect::PostgreSql, $identity));
    }
}
