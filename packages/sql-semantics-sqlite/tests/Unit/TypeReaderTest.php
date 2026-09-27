<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\TypeReader;
use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeName;
use Tests\Contract\Resolved;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Semantics::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeDescriptor::class)]
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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Builtin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(TypeName::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Affinity::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\IntervalFields::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TypeReaderTest extends TestCase
{
    /**
     * @param array{length?: int, precision?: int, scale?: int, unsigned?: bool, zerofill?: bool, binaryCollation?: bool, characterSet?: string, members?: int, autoIncrement?: bool, arrayDimensions?: int, intervalFields?: \SqlSemantics\Statement\Declaration\IntervalFields, nullability?: Nullability} $facts
     */
    #[DataProvider('providerDeclarations')]
    public function testReadKeepsTheDeclaredNameAndItsAffinity(string $declaration, Builtin|TypeName $name, Affinity $affinity, array $facts = []): void
    {
        $type = Resolved::of((new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (c ' . $declaration . ')', []))->declarations[0]->columns[0]->type;
        self::assertTrue($type->is($name));
        self::assertSame($affinity, $type->affinity);
        self::assertSame($facts['length'] ?? null, $type->length);
        self::assertSame($facts['precision'] ?? null, $type->precision);
        self::assertSame($facts['scale'] ?? null, $type->scale);
        self::assertFalse($type->unsigned);
    }

    /**
     * @return iterable<string, array{string, Builtin|TypeName, Affinity, 3?: array{length?: int, precision?: int, scale?: int, unsigned?: bool, zerofill?: bool, binaryCollation?: bool, characterSet?: string, members?: int, autoIncrement?: bool, arrayDimensions?: int, intervalFields?: \SqlSemantics\Statement\Declaration\IntervalFields, nullability?: Nullability}}>
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
        self::assertSame($expected, (new TypeReader())->affinity($spelling));
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
        self::assertSame($expected ? Nullability::NotNull : Nullability::MaybeNull, Resolved::of((new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (c ' . $declaration . ')', []))->declarations[0]->columns[0]->nullability);
    }

    public function testModifiersKeepIntegerArgumentsInStandardSlotsOnly(): void
    {
        $reader = new TypeReader();
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
        $reader = new TypeReader();
        self::assertTrue($reader->strict((new SqliteParser())->parse('CREATE TABLE t (c ANY) STRICT')));
        self::assertFalse($reader->strict((new SqliteParser())->parse('CREATE TABLE t (c ANY) WITHOUT ROWID')));
    }
}
