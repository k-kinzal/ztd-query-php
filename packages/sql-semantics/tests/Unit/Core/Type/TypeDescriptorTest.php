<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Schema as SchemaFacade;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;

#[CoversClass(\SqlSemantics\Core\Type\TypeDescriptor::class)]
#[CoversClass(\SqlSemantics\Core\Type\Builtin::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeName::class)]
#[CoversClass(\SqlSemantics\Core\Type\TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[CoversClass(\SqlSemantics\Core\Schema\Invariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaFacade::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Core\Schema::class)]
#[CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[Medium]
final class TypeDescriptorTest extends TestCase
{
    public function testDistinguishesStorageAffinityFromDeclaredType(): void
    {
        $table = (new SchemaFacade(SqliteDialect::Sqlite))->analyze('CREATE TABLE users (code VARCHAR(20))')->tables[0];
        self::assertSame(\SqlSemantics\Core\Type\Builtin::VarChar, $table->columns[0]->type->name);
        self::assertSame(20, $table->columns[0]->type->length);
        self::assertNull($table->columns[0]->type->precision);
        self::assertSame(\SqlSemantics\Core\Type\Affinity::Text, $table->columns[0]->type->affinity);
    }

    public function testIsComparesOnlyTheTypeIdentity(): void
    {
        $type = new \SqlSemantics\Core\Type\TypeDescriptor(SqliteDialect::Sqlite, \SqlSemantics\Core\Type\Builtin::VarChar, length: 20);
        self::assertTrue($type->is(\SqlSemantics\Core\Type\Builtin::VarChar));
        self::assertFalse($type->is(\SqlSemantics\Core\Type\Builtin::Text));
        self::assertFalse($type->is(new \SqlSemantics\Core\Type\TypeName(['varchar'])));
        $named = new \SqlSemantics\Core\Type\TypeDescriptor(SqliteDialect::Sqlite, new \SqlSemantics\Core\Type\TypeName(['UNSIGNED', 'BIG', 'INT']));
        self::assertTrue($named->is(new \SqlSemantics\Core\Type\TypeName(['UNSIGNED', 'BIG', 'INT'])));
        self::assertFalse($named->is(\SqlSemantics\Core\Type\Builtin::BigInt));
    }

    public function testLabelNamesBuiltinAndNamedTypesForDiagnostics(): void
    {
        self::assertSame('double precision', (new \SqlSemantics\Core\Type\TypeDescriptor(SqliteDialect::Sqlite, \SqlSemantics\Core\Type\Builtin::DoublePrecision))->label());
        self::assertSame('app.money', (new \SqlSemantics\Core\Type\TypeDescriptor(SqliteDialect::Sqlite, new \SqlSemantics\Core\Type\TypeName(['app', 'money'])))->label());
    }

    public function testKeepsOrthogonalDeclarationFactsApartFromTheName(): void
    {
        $column = (new SchemaFacade(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (n INT UNSIGNED ZEROFILL, s VARCHAR(10) CHARSET utf8mb4)')->tables[0]->columns;
        self::assertSame(\SqlSemantics\Core\Type\Builtin::Integer, $column[0]->type->name);
        self::assertTrue($column[0]->type->unsigned);
        self::assertTrue($column[0]->type->zerofill);
        self::assertSame(\SqlSemantics\Core\Type\Builtin::VarChar, $column[1]->type->name);
        self::assertSame(10, $column[1]->type->length);
        self::assertSame('utf8mb4', $column[1]->type->characterSet);
        $array = (new SchemaFacade(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (v INTEGER[], n NUMERIC(2,-3))')->tables[0]->columns;
        self::assertSame(1, $array[0]->type->arrayDimensions);
        self::assertSame([2, -3], [$array[1]->type->precision, $array[1]->type->scale]);
    }
}
