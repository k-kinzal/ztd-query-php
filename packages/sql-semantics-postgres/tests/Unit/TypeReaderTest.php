<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\TypeReader;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\IntervalFields;
use SqlSemantics\Statement\Declaration\TypeName;
use Tests\Contract\Resolved;

#[\PHPUnit\Framework\Attributes\CoversClass(SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Semantics::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Nullability::class)]
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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(TypeReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Builtin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(TypeName::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Affinity::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(IntervalFields::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TypeReaderTest extends TestCase
{
    /**
     * @param array{length?: int, precision?: int, scale?: int, unsigned?: bool, zerofill?: bool, binaryCollation?: bool, characterSet?: string, members?: int, autoIncrement?: bool, arrayDimensions?: int, intervalFields?: IntervalFields, nullability?: \SqlSemantics\Statement\Declaration\Nullability} $facts
     */
    #[DataProvider('providerDeclarations')]
    public function testReadSeparatesTheTypeIdentityFromItsIndependentFacts(string $declaration, Builtin|TypeName $name, array $facts): void
    {
        $column = Resolved::of((new Semantics(Dialect::PostgreSql))->analyze('CREATE TABLE t (c ' . $declaration . ')', []))->declarations[0]->columns[0];
        $type = $column->type;
        self::assertTrue($type->is($name));
        self::assertSame($facts['length'] ?? null, $type->length);
        self::assertSame($facts['precision'] ?? null, $type->precision);
        self::assertSame($facts['scale'] ?? null, $type->scale);
        self::assertSame($facts['arrayDimensions'] ?? 0, $type->arrayDimensions);
        self::assertSame($facts['intervalFields'] ?? null, $type->intervalFields);
        self::assertSame($facts['autoIncrement'] ?? false, $column->autoIncrement);
        self::assertSame($facts['nullability'] ?? \SqlSemantics\Statement\Declaration\Nullability::MaybeNull, $column->nullability);
        self::assertFalse($type->unsigned);
        self::assertNull($type->characterSet);
        self::assertNull($type->affinity);
    }

    /**
     * @return iterable<string, array{string, Builtin|TypeName, array{length?: int, precision?: int, scale?: int, unsigned?: bool, zerofill?: bool, binaryCollation?: bool, characterSet?: string, members?: int, autoIncrement?: bool, arrayDimensions?: int, intervalFields?: IntervalFields, nullability?: \SqlSemantics\Statement\Declaration\Nullability}}>
     */
    public static function providerDeclarations(): iterable
    {
        yield 'array' => ['INTEGER[]', Builtin::Integer, ['arrayDimensions' => 1]];
        yield 'nested array with bounds' => ['INT[3][]', Builtin::Integer, ['arrayDimensions' => 2]];
        yield 'array word' => ['INTEGER ARRAY', Builtin::Integer, ['arrayDimensions' => 1]];
        yield 'array word with bound' => ['INTEGER ARRAY[2]', Builtin::Integer, ['arrayDimensions' => 1]];
        yield 'negative scale' => ['NUMERIC(2,-3)', Builtin::Numeric, ['precision' => 2, 'scale' => -3]];
        yield 'serial' => ['SERIAL', Builtin::Integer, ['autoIncrement' => true, 'nullability' => \SqlSemantics\Statement\Declaration\Nullability::NotNull]];
        yield 'bigserial' => ['BIGSERIAL', Builtin::BigInt, ['autoIncrement' => true, 'nullability' => \SqlSemantics\Statement\Declaration\Nullability::NotNull]];
        yield 'smallserial' => ['pg_catalog.serial2', Builtin::SmallInt, ['autoIncrement' => true, 'nullability' => \SqlSemantics\Statement\Declaration\Nullability::NotNull]];
        yield 'catalog qualified' => ['pg_catalog.int4', Builtin::Integer, []];
        yield 'character varying' => ['CHARACTER VARYING(10)', Builtin::VarChar, ['length' => 10]];
        yield 'char' => ['CHAR', Builtin::Char, []];
        yield 'nchar varying' => ['NCHAR VARYING(3)', Builtin::VarChar, ['length' => 3]];
        yield 'timestamp with time zone' => ['TIMESTAMP(3) WITH TIME ZONE', Builtin::TimestampTz, ['precision' => 3]];
        yield 'time without time zone' => ['TIME WITHOUT TIME ZONE', Builtin::Time, []];
        yield 'timestamptz name' => ['timestamptz(3)', Builtin::TimestampTz, ['precision' => 3]];
        yield 'interval fields with precision' => ['INTERVAL DAY TO SECOND(3)', Builtin::Interval, ['precision' => 3, 'intervalFields' => IntervalFields::DayToSecond]];
        yield 'interval precision' => ['INTERVAL(2)', Builtin::Interval, ['precision' => 2]];
        yield 'interval single field' => ['INTERVAL YEAR TO MONTH', Builtin::Interval, ['intervalFields' => IntervalFields::YearToMonth]];
        yield 'bit varying' => ['BIT VARYING(8)', Builtin::BitVarying, ['length' => 8]];
        yield 'bit' => ['BIT', Builtin::Bit, []];
        yield 'varbit name' => ['varbit(4)', Builtin::BitVarying, ['length' => 4]];
        yield 'float wide' => ['FLOAT(30)', Builtin::DoublePrecision, ['precision' => 30]];
        yield 'float narrow' => ['FLOAT(10)', Builtin::Real, ['precision' => 10]];
        yield 'float default' => ['FLOAT', Builtin::DoublePrecision, []];
        yield 'double precision' => ['DOUBLE PRECISION', Builtin::DoublePrecision, []];
        yield 'json keyword' => ['JSON', Builtin::Json, []];
        yield 'jsonb name' => ['jsonb', Builtin::Jsonb, []];
        yield 'text keyword name' => ['text', Builtin::Text, []];
        yield 'quoted char' => ['"char"', Builtin::QuotedChar, []];
        yield 'bpchar' => ['bpchar(2)', Builtin::Char, ['length' => 2]];
        yield 'range' => ['daterange', Builtin::DateRange, []];
        yield 'dec' => ['DEC', Builtin::Numeric, []];
        yield 'boolean' => ['BOOLEAN', Builtin::Boolean, []];
        yield 'user type with modifiers' => ['app.money_amount(3)', new TypeName(['app', 'money_amount']), []];
        yield 'user type with expression modifiers' => ["mytype('a', 1+2)", new TypeName(['mytype']), []];
        yield 'unknown catalog spelling' => ['"integer"', new TypeName(['integer']), []];
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['SETOF int', 'cannot be declared SETOF'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['NUMERIC(1+2)', 'must be constants'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['NUMERIC(-1)', 'Invalid type modifier'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['NUMERIC(1,2,3)', 'Too many type modifiers'])]
    public function testReadRejectsDeclarationsOutsideTheColumnTypeSurface(string $declaration, string $message): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage($message);
        Resolved::of((new Semantics(Dialect::PostgreSql))->analyze('CREATE TABLE t (c ' . $declaration . ')', []));
    }

    public function testReadLeavesAStringTypeModifierUnreadRatherThanInvalid(): void
    {
        $reference = Resolved::of((new Semantics(Dialect::PostgreSql))->analyze("CREATE TABLE t (c NUMERIC('10'))", []))->references[0];
        self::assertNull($reference->table);
        self::assertSame([], Resolved::of((new Semantics(Dialect::PostgreSql))->analyze("CREATE TABLE t (c NUMERIC('10'))", []))->declarations);
    }

    public function testDimensionsCountBracketsOrTheArrayWord(): void
    {
        $reader = new TypeReader();
        self::assertSame(2, $reader->dimensions((new PostgreSqlParser())->parse('CREATE TABLE t (c INT[3][])')->find('Typename')[0]));
        self::assertSame(1, $reader->dimensions((new PostgreSqlParser())->parse('CREATE TABLE t (c INT ARRAY)')->find('Typename')[0]));
        self::assertSame(0, $reader->dimensions((new PostgreSqlParser())->parse('CREATE TABLE t (c INT)')->find('Typename')[0]));
    }

    public function testNumericReadsKeywordNumbersAndTheirModifiers(): void
    {
        $facts = ['length' => null, 'precision' => null, 'scale' => null, 'fields' => null, 'autoIncrement' => false];
        $node = (new PostgreSqlParser())->parse('CREATE TABLE t (c DECIMAL(6, 2))')->find('Numeric')[0];
        self::assertSame(Builtin::Numeric, (new TypeReader())->numeric($node, $facts));
        self::assertSame([6, 2], [$facts['precision'], $facts['scale']]);
    }

    public function testBitReadsVaryingAndLength(): void
    {
        $facts = ['length' => null, 'precision' => null, 'scale' => null, 'fields' => null, 'autoIncrement' => false];
        $node = (new PostgreSqlParser())->parse('CREATE TABLE t (c BIT VARYING(8))')->find('Bit')[0];
        self::assertSame(Builtin::BitVarying, (new TypeReader())->bit($node, $facts));
        self::assertSame(8, $facts['length']);
    }

    public function testCharacterReadsVaryingAndLength(): void
    {
        $facts = ['length' => null, 'precision' => null, 'scale' => null, 'fields' => null, 'autoIncrement' => false];
        $node = (new PostgreSqlParser())->parse('CREATE TABLE t (c NATIONAL CHARACTER(4))')->find('Character')[0];
        self::assertSame(Builtin::Char, (new TypeReader())->character($node, $facts));
        self::assertSame(4, $facts['length']);
    }

    public function testDatetimeSeparatesTimeZoneFromPrecision(): void
    {
        $facts = ['length' => null, 'precision' => null, 'scale' => null, 'fields' => null, 'autoIncrement' => false];
        $node = (new PostgreSqlParser())->parse('CREATE TABLE t (c TIME(2) WITH TIME ZONE)')->find('ConstDatetime')[0];
        self::assertSame(Builtin::TimeTz, (new TypeReader())->datetime($node, $facts));
        self::assertSame(2, $facts['precision']);
    }

    public function testIntervalReadsFieldsAndSecondsPrecision(): void
    {
        $facts = ['length' => null, 'precision' => null, 'scale' => null, 'fields' => null, 'autoIncrement' => false];
        $node = (new PostgreSqlParser())->parse('CREATE TABLE t (c INTERVAL HOUR TO SECOND(1))')->find('SimpleTypename')[0];
        self::assertSame(Builtin::Interval, (new TypeReader())->interval($node, $facts));
        self::assertSame(IntervalFields::HourToSecond, $facts['fields']);
        self::assertSame(1, $facts['precision']);
    }

    public function testGenericResolvesCatalogNamesAndKeepsOtherNames(): void
    {
        $facts = ['length' => null, 'precision' => null, 'scale' => null, 'fields' => null, 'autoIncrement' => false];
        $reader = new TypeReader();
        self::assertSame(Builtin::Numeric, $reader->generic((new PostgreSqlParser())->parse('CREATE TABLE t (c pg_catalog.numeric(5,1))')->find('GenericType')[0], $facts));
        self::assertSame([5, 1], [$facts['precision'], $facts['scale']]);
        $named = $reader->generic((new PostgreSqlParser())->parse('CREATE TABLE t (c Other.Money)')->find('GenericType')[0], $facts);
        self::assertInstanceOf(TypeName::class, $named);
        self::assertSame(['other', 'money'], $named->parts);
    }

    public function testAssignPlacesModifiersBySlot(): void
    {
        $reader = new TypeReader();
        $facts = ['length' => null, 'precision' => null, 'scale' => null, 'fields' => null, 'autoIncrement' => false];
        $reader->assign(Builtin::Numeric, [8, 3], $facts);
        self::assertSame([null, 8, 3], [$facts['length'], $facts['precision'], $facts['scale']]);
        $facts = ['length' => null, 'precision' => null, 'scale' => null, 'fields' => null, 'autoIncrement' => false];
        $reader->assign(Builtin::TimestampTz, [4], $facts);
        self::assertSame([null, 4, null], [$facts['length'], $facts['precision'], $facts['scale']]);
        $facts = ['length' => null, 'precision' => null, 'scale' => null, 'fields' => null, 'autoIncrement' => false];
        $reader->assign(Builtin::BitVarying, [16], $facts);
        self::assertSame([16, null, null], [$facts['length'], $facts['precision'], $facts['scale']]);
        $reader->assign(Builtin::Uuid, [], $facts);
        self::assertSame(16, $facts['length']);
    }

    public function testVaryingDetectsBothSpellings(): void
    {
        $reader = new TypeReader();
        self::assertTrue($reader->varying((new PostgreSqlParser())->parse('CREATE TABLE t (c VARCHAR)')->find('Character')[0]));
        self::assertFalse($reader->varying((new PostgreSqlParser())->parse('CREATE TABLE t (c CHAR)')->find('Character')[0]));
    }

    public function testIconstReadsTheSingleConstantOfAKeywordType(): void
    {
        $reader = new TypeReader();
        self::assertSame(7, $reader->iconst((new PostgreSqlParser())->parse('CREATE TABLE t (c CHAR(7))')->find('Character')[0]));
        self::assertNull($reader->iconst((new PostgreSqlParser())->parse('CREATE TABLE t (c CHAR)')->find('Character')[0]));
    }

    public function testModifiersReadSignedIntegerConstants(): void
    {
        $node = (new PostgreSqlParser())->parse('CREATE TABLE t (c NUMERIC(4, -2))')->find('Numeric')[0];
        self::assertSame([4, -2], (new TypeReader())->modifiers($node, 2, $node));
    }

    public function testIntegerRejectsAnOverflowingConstant(): void
    {
        $node = new Node('Iconst', 0, []);
        self::assertSame(12, (new TypeReader())->integer([new Token(1, 'ICONST', '12', 0)], $node));
        $this->expectException(SemanticException::class);
        (new TypeReader())->integer([new Token(1, 'ICONST', '99999999999999999999', 0)], $node);
    }

    public function testNumericSizeSeparatesWrittenAndEffectiveScale(): void
    {
        $type = (new Semantics(Dialect::PostgreSql))->type('DECIMAL(5)')->type;
        self::assertNull($type->scale);
        self::assertSame(0, $type->effectiveNumericSize?->scale);
    }

    public function testSupportsTellsPostgreSqlTypesFromOthers(): void
    {
        self::assertTrue(TypeReader::supports(Builtin::Integer));
        self::assertTrue(TypeReader::supports(Builtin::TimestampTz));
        self::assertFalse(TypeReader::supports(Builtin::TinyInt));
        self::assertFalse(TypeReader::supports(Builtin::Any));
    }
}
