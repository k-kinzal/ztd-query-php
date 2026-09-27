<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use Tests\Contract\Resolved;

#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDescriptor::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Semantics::class)]
#[CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
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
        $table = Resolved::of((new Semantics(SqliteDialect::Sqlite))->analyze('CREATE TABLE users (code VARCHAR(20))', []))->declarations[0];
        self::assertSame(\SqlSemantics\Statement\Declaration\Builtin::VarChar, $table->columns[0]->type->name);
        self::assertSame(20, $table->columns[0]->type->length);
        self::assertNull($table->columns[0]->type->precision);
        self::assertSame(\SqlSemantics\Statement\Declaration\Affinity::Text, $table->columns[0]->type->affinity);
    }

    public function testIsComparesOnlyTheTypeIdentity(): void
    {
        $type = new \SqlSemantics\Statement\Declaration\TypeDescriptor(\SqlSemantics\Statement\Declaration\Builtin::VarChar, length: 20);
        self::assertTrue($type->is(\SqlSemantics\Statement\Declaration\Builtin::VarChar));
        self::assertFalse($type->is(\SqlSemantics\Statement\Declaration\Builtin::Text));
        self::assertFalse($type->is(new \SqlSemantics\Statement\Declaration\TypeName(['varchar'])));
        $named = new \SqlSemantics\Statement\Declaration\TypeDescriptor(new \SqlSemantics\Statement\Declaration\TypeName(['UNSIGNED', 'BIG', 'INT']));
        self::assertTrue($named->is(new \SqlSemantics\Statement\Declaration\TypeName(['UNSIGNED', 'BIG', 'INT'])));
        self::assertFalse($named->is(\SqlSemantics\Statement\Declaration\Builtin::BigInt));
    }

    public function testLabelNamesBuiltinAndNamedTypesForDiagnostics(): void
    {
        self::assertSame('double precision', (new \SqlSemantics\Statement\Declaration\TypeDescriptor(\SqlSemantics\Statement\Declaration\Builtin::DoublePrecision))->label());
        self::assertSame('app.money', (new \SqlSemantics\Statement\Declaration\TypeDescriptor(new \SqlSemantics\Statement\Declaration\TypeName(['app', 'money'])))->label());
    }

    public function testKeepsOrthogonalDeclarationFactsApartFromTheName(): void
    {
        $column = Resolved::of((new Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (n INT UNSIGNED ZEROFILL, s VARCHAR(10) CHARSET utf8mb4)', []))->declarations[0]->columns;
        self::assertSame(\SqlSemantics\Statement\Declaration\Builtin::Integer, $column[0]->type->name);
        self::assertTrue($column[0]->type->unsigned);
        self::assertTrue($column[0]->type->zerofill);
        self::assertSame(\SqlSemantics\Statement\Declaration\Builtin::VarChar, $column[1]->type->name);
        self::assertSame(10, $column[1]->type->length);
        self::assertSame('utf8mb4', $column[1]->type->characterSet);
        $array = Resolved::of((new Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (v INTEGER[], n NUMERIC(2,-3))', []))->declarations[0]->columns;
        self::assertSame(1, $array[0]->type->arrayDimensions);
        self::assertSame([2, -3], [$array[1]->type->precision, $array[1]->type->scale]);
    }
}
