<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Core\Type\Affinity;
use SqlSemantics\Core\Type\Builtin;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeName;
use SqlSemantics\Facade\Schema;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\TypeReader;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Schema::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(TypeReader::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TypeReaderTest extends TestCase
{
    /**
     * @param array{length?: int, precision?: int, scale?: int, unsigned?: bool, zerofill?: bool, binaryCollation?: bool, characterSet?: string, members?: int, autoIncrement?: bool, arrayDimensions?: int, intervalFields?: \SqlSemantics\Core\Type\IntervalFields, nullability?: Nullability} $facts
     */
    #[DataProvider('providerDeclarations')]
    public function testReadKeepsTheDeclaredNameAndItsAffinity(string $declaration, Builtin|TypeName $name, Affinity $affinity, array $facts = []): void
    {
        $type = (new Schema(Dialect::Sqlite))->analyze('CREATE TABLE t (c ' . $declaration . ')')->tables[0]->columns[0]->type;
        self::assertTrue($type->is($name));
        self::assertSame($affinity, $type->affinity);
        self::assertSame($facts['length'] ?? null, $type->length);
        self::assertSame($facts['precision'] ?? null, $type->precision);
        self::assertSame($facts['scale'] ?? null, $type->scale);
        self::assertFalse($type->unsigned);
    }

    /**
     * @return iterable<string, array{string, Builtin|TypeName, Affinity, 3?: array{length?: int, precision?: int, scale?: int, unsigned?: bool, zerofill?: bool, binaryCollation?: bool, characterSet?: string, members?: int, autoIncrement?: bool, arrayDimensions?: int, intervalFields?: \SqlSemantics\Core\Type\IntervalFields, nullability?: Nullability}}>
     */
    public static function providerDeclarations(): iterable
    {
        yield 'integer' => ['INTEGER', Builtin::Integer, Affinity::Integer];
        yield 'int' => ['INT', Builtin::Integer, Affinity::Integer];
        yield 'varchar' => ['VARCHAR(20)', Builtin::VarChar, Affinity::Text, ['length' => 20]];
        yield 'decimal' => ['DECIMAL(10, 2)', Builtin::Numeric, Affinity::Numeric, ['precision' => 10, 'scale' => 2]];
        yield 'unsigned big int is a name' => ['UNSIGNED BIG INT', new TypeName(['UNSIGNED', 'BIG', 'INT']), Affinity::Integer];
        yield 'non-integer arguments are not interpreted' => ['NUMERIC(+3,-1.5)', Builtin::Numeric, Affinity::Numeric];
        yield 'quoted any' => ['"ANY"', Builtin::Any, Affinity::Numeric];
        yield 'varying character' => ['VARYING CHARACTER(255)', Builtin::VarChar, Affinity::Text, ['length' => 255]];
        yield 'untyped' => ['', Builtin::Dynamic, Affinity::Blob];
        yield 'float' => ['FLOAT', Builtin::Real, Affinity::Real];
        yield 'datetime precision' => ['DATETIME(3)', Builtin::DateTime, Affinity::Numeric, ['precision' => 3]];
        yield 'named with argument' => ['FOO(5)', new TypeName(['FOO']), Affinity::Numeric];
        yield 'floating point' => ['FLOATING POINT', new TypeName(['FLOATING', 'POINT']), Affinity::Integer];
        yield 'integer display width' => ['INT(5)', Builtin::Integer, Affinity::Integer, ['length' => 5]];
    }

    #[DataProvider('providerAffinities')]
    public function testAffinityUsesTheDocumentedPrecedence(string $spelling, Affinity $expected): void
    {
        self::assertSame($expected, (new TypeReader(Dialect::Sqlite))->affinity($spelling));
    }

    /**
     * @return iterable<string, array{string, Affinity}>
     */
    public static function providerAffinities(): iterable
    {
        yield 'FLOATING POINT' => ['FLOATING POINT', Affinity::Integer];
        yield 'CHARINT' => ['CHARINT', Affinity::Integer];
        yield 'VARCHAR' => ['VARCHAR', Affinity::Text];
        yield 'CLOB' => ['CLOB', Affinity::Text];
        yield 'TEXT' => ['TEXT', Affinity::Text];
        yield 'empty' => ['', Affinity::Blob];
        yield 'BLOB' => ['BLOB', Affinity::Blob];
        yield 'REAL' => ['REAL', Affinity::Real];
        yield 'FLOAT' => ['FLOAT', Affinity::Real];
        yield 'DOUBLE' => ['DOUBLE', Affinity::Real];
        yield 'BOOLEAN' => ['BOOLEAN', Affinity::Numeric];
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['INTEGER PRIMARY KEY', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['integer PRIMARY KEY', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['"INTEGER" PRIMARY KEY', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['INT PRIMARY KEY', false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['INTEGER(5) PRIMARY KEY', false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['UNSIGNED INTEGER PRIMARY KEY', false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['PRIMARY KEY', false])]
    public function testRowidAliasRequiresTheExactSpellingInteger(string $declaration, bool $expected): void
    {
        $tree = (new SqliteParser())->parse('CREATE TABLE t (c ' . $declaration . ')');
        self::assertSame($expected, TypeReader::rowidAlias($tree->find('columnname')[0]));
        self::assertSame($expected ? Nullability::NotNull : Nullability::MaybeNull, (new Schema(Dialect::Sqlite))->analyze('CREATE TABLE t (c ' . $declaration . ')')->tables[0]->columns[0]->nullability);
    }

    public function testModifiersKeepIntegerArgumentsInStandardSlotsOnly(): void
    {
        $reader = new TypeReader(Dialect::Sqlite);
        self::assertSame([20, null, null], $reader->modifiers(Builtin::VarChar, [20]));
        self::assertSame([null, 10, 2], $reader->modifiers(Builtin::Numeric, [10, 2]));
        self::assertSame([null, 3, null], $reader->modifiers(Builtin::DateTime, [3]));
        self::assertSame([null, null, null], $reader->modifiers(Builtin::VarChar, [10, 2]));
        self::assertSame([null, null, null], $reader->modifiers(Builtin::VarChar, [null]));
        self::assertSame([null, null, null], $reader->modifiers(Builtin::VarChar, [-1]));
        self::assertSame([null, null, null], $reader->modifiers(new TypeName(['FOO']), [5]));
    }

    public function testStrictRecognizesTheTableOption(): void
    {
        $reader = new TypeReader(Dialect::Sqlite);
        self::assertTrue($reader->strict((new SqliteParser())->parse('CREATE TABLE t (c ANY) STRICT')));
        self::assertFalse($reader->strict((new SqliteParser())->parse('CREATE TABLE t (c ANY) WITHOUT ROWID')));
    }
}
