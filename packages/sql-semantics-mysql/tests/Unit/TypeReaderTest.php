<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\Node;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\TypeReader;
use SqlSemantics\Statement\Declaration\Builtin;
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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(TypeReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Builtin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Affinity::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\IntervalFields::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TypeReaderTest extends TestCase
{
    /**
     * @param array{length?: int, precision?: int, scale?: int, unsigned?: bool, zerofill?: bool, binaryCollation?: bool, characterSet?: string, members?: int, autoIncrement?: bool, arrayDimensions?: int, intervalFields?: \SqlSemantics\Statement\Declaration\IntervalFields, nullability?: \SqlSemantics\Statement\Declaration\Nullability} $facts
     */
    #[DataProvider('providerDeclarations')]
    public function testReadSeparatesTheTypeIdentityFromItsIndependentFacts(string $declaration, Builtin $name, array $facts, ?string $version = null): void
    {
        $column = Resolved::of((new Semantics(Dialect::MySql, grammarVersion: $version))->analyze('CREATE TABLE t (c ' . $declaration . ')', []))->declarations[0]->columns[0];
        $type = $column->type;
        self::assertSame($name, $type->name);
        self::assertSame($facts['length'] ?? null, $type->length);
        self::assertSame($facts['precision'] ?? null, $type->precision);
        self::assertSame($facts['scale'] ?? null, $type->scale);
        self::assertSame($facts['unsigned'] ?? false, $type->unsigned);
        self::assertSame($facts['zerofill'] ?? false, $type->zerofill);
        self::assertSame($facts['binaryCollation'] ?? false, $type->binaryCollation);
        self::assertSame($facts['characterSet'] ?? null, $type->characterSet);
        self::assertCount($facts['members'] ?? 0, $type->members);
        self::assertSame($facts['autoIncrement'] ?? false, $column->autoIncrement);
        self::assertNull($type->affinity);
    }

    /**
     * @return iterable<string, array{string, Builtin, array{length?: int, precision?: int, scale?: int, unsigned?: bool, zerofill?: bool, binaryCollation?: bool, characterSet?: string, members?: int, autoIncrement?: bool, arrayDimensions?: int, intervalFields?: \SqlSemantics\Statement\Declaration\IntervalFields, nullability?: \SqlSemantics\Statement\Declaration\Nullability}, 3?: string}>
     */
    public static function providerDeclarations(): iterable
    {
        yield 'unsigned zerofill' => ['INT UNSIGNED ZEROFILL', Builtin::Integer, ['unsigned' => true, 'zerofill' => true]];
        yield 'zerofill implies unsigned' => ['INT(11) ZEROFILL', Builtin::Integer, ['length' => 11, 'unsigned' => true, 'zerofill' => true]];
        yield 'character set' => ['VARCHAR(10) CHARSET utf8mb4', Builtin::VarChar, ['length' => 10, 'characterSet' => 'utf8mb4']];
        yield 'character set and binary' => ['VARCHAR(10) CHARACTER SET utf8mb4 BINARY', Builtin::VarChar, ['length' => 10, 'characterSet' => 'utf8mb4', 'binaryCollation' => true]];
        yield 'binary before character set' => ['VARCHAR(255) BINARY CHARSET latin1', Builtin::VarChar, ['length' => 255, 'characterSet' => 'latin1', 'binaryCollation' => true]];
        yield 'int1' => ['INT1', Builtin::TinyInt, []];
        yield 'middleint' => ['MIDDLEINT(3)', Builtin::MediumInt, ['length' => 3]];
        yield 'bool is tinyint(1)' => ['BOOL', Builtin::TinyInt, ['length' => 1]];
        yield 'serial' => ['SERIAL', Builtin::BigInt, ['unsigned' => true, 'autoIncrement' => true]];
        yield 'char byte is binary' => ['CHAR(5) BYTE', Builtin::Binary, ['length' => 5]];
        yield 'text with binary character set is blob' => ['TEXT(100) CHARSET binary', Builtin::Blob, ['length' => 100]];
        yield 'national varying' => ['NATIONAL CHAR VARYING(3)', Builtin::VarChar, ['length' => 3, 'characterSet' => 'utf8mb3']];
        yield 'nchar' => ['NCHAR(3)', Builtin::Char, ['length' => 3, 'characterSet' => 'utf8mb3']];
        yield 'long varbinary' => ['LONG VARBINARY', Builtin::MediumBlob, []];
        yield 'long varchar ascii' => ['LONG VARCHAR ASCII', Builtin::MediumText, ['characterSet' => 'latin1']];
        yield 'long' => ['LONG', Builtin::MediumText, []];
        yield 'float wider than 24 bits' => ['FLOAT(30)', Builtin::DoublePrecision, ['precision' => 30]];
        yield 'float with scale' => ['FLOAT(7,4)', Builtin::Real, ['precision' => 7, 'scale' => 4]];
        yield 'double with scale' => ['DOUBLE(10,2)', Builtin::DoublePrecision, ['precision' => 10, 'scale' => 2]];
        yield 'real is double' => ['REAL', Builtin::DoublePrecision, []];
        yield 'fixed' => ['FIXED(5,2)', Builtin::Numeric, ['precision' => 5, 'scale' => 2]];
        yield 'enum with binary character set' => ["ENUM('a','b') CHARSET binary", Builtin::Enum, ['characterSet' => 'binary', 'members' => 2]];
        yield 'set unicode' => ["SET('x') UNICODE", Builtin::Set, ['characterSet' => 'ucs2', 'members' => 1]];
        yield 'datetime precision' => ['DATETIME(6)', Builtin::DateTime, ['precision' => 6]];
        yield 'timestamp precision' => ['TIMESTAMP(3)', Builtin::Timestamp, ['precision' => 3]];
        yield 'year ignores a sign' => ['YEAR(4) UNSIGNED', Builtin::Year, ['length' => 4]];
        yield 'bit' => ['BIT(8)', Builtin::Bit, ['length' => 8]];
        yield 'spatial' => ['POINT SRID 4326', Builtin::Point, []];
        yield 'json' => ['JSON', Builtin::Json, []];
        yield 'char varying' => ['CHAR VARYING(2)', Builtin::VarChar, ['length' => 2]];
        yield 'legacy unsigned' => ['INT UNSIGNED ZEROFILL', Builtin::Integer, ['unsigned' => true, 'zerofill' => true], 'mysql-5.6.51'];
        yield 'legacy synonym' => ['INT1', Builtin::TinyInt, [], 'mysql-5.6.51'];
        yield 'legacy serial' => ['SERIAL', Builtin::BigInt, ['unsigned' => true, 'autoIncrement' => true], 'mysql-5.6.51'];
        yield 'legacy tinytext' => ['TINYTEXT', Builtin::TinyText, [], 'mysql-5.7.44'];
        yield 'legacy national varchar' => ['NATIONAL VARCHAR(3)', Builtin::VarChar, ['length' => 3, 'characterSet' => 'utf8mb3'], 'mysql-5.6.51'];
        yield 'legacy enum' => ["ENUM('a')", Builtin::Enum, ['members' => 1], 'mysql-5.6.51'];
        yield 'vector' => ['VECTOR(3)', Builtin::Vector, ['length' => 3], 'mysql-9.1.0'];
    }

    public function testReadDeclaresSerialAsANonnullableUniqueGeneratedColumn(): void
    {
        $table = Resolved::of((new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (id SERIAL, note TEXT)', []))->declarations[0];
        self::assertSame(\SqlSemantics\Statement\Declaration\Nullability::NotNull, $table->columns[0]->nullability);
        self::assertTrue($table->columns[0]->autoIncrement);
        self::assertSame([\SqlSemantics\Statement\Declaration\ConstraintKind::Unique], array_column($table->constraints, 'kind'));
        self::assertSame(['id'], $table->constraints[0]->columns);
        self::assertTrue($table->constraints[0]->inline);
    }

    public function testReadRejectsArgumentsTheDatabaseCannotStore(): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Invalid type argument');
        Resolved::of((new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (c VARCHAR(70000000000000000000000))', []));
    }

    public function testKindFollowsTheLexerTokenRatherThanTheSpelling(): void
    {
        $reader = new TypeReader();
        $node = new Node('type', 0, []);
        self::assertSame(Builtin::TinyInt, $reader->kind([new Token(1, 'TINYINT_SYM', 'INT1', 0)], $node));
        self::assertSame(Builtin::VarChar, $reader->kind([new Token(1, 'NCHAR_SYM', 'NCHAR', 0), new Token(2, 'VARYING', 'VARYING', 6)], $node));
        self::assertSame(Builtin::MediumBlob, $reader->kind([new Token(1, 'LONG_SYM', 'LONG', 0), new Token(2, 'VARBINARY_SYM', 'VARBINARY', 5)], $node));
    }

    public function testKindRejectsATokenOutsideTheTypeVocabulary(): void
    {
        $this->expectException(SemanticException::class);
        (new TypeReader())->kind([new Token(1, 'IDENT', 'custom', 0)], new Node('type', 0, []));
    }

    public function testLeadingStopsAtArgumentsAndAttributes(): void
    {
        $tokens = (new MySqlParser())->parse('CREATE TABLE t (c NATIONAL CHAR VARYING(3) BINARY)')->find('type')[0]->tokens();
        self::assertSame(['NATIONAL', 'CHAR', 'VARYING'], array_map(static fn (Token $token): string => $token->text, (new TypeReader())->leading($tokens)));
        $tokens = (new MySqlParser())->parse('CREATE TABLE t (c CHAR CHARACTER SET utf8mb4)')->find('type')[0]->tokens();
        self::assertSame(['CHAR'], array_map(static fn (Token $token): string => $token->text, (new TypeReader())->leading($tokens)));
    }

    public function testAttributesReadEveryFactAfterTheTypeName(): void
    {
        $tokens = (new MySqlParser())->parse('CREATE TABLE t (c VARCHAR(10) CHARACTER SET Latin1 BINARY)')->find('type')[0]->tokens();
        self::assertSame(['unsigned' => false, 'zerofill' => false, 'binary' => true, 'octets' => false, 'characterSet' => 'latin1'], (new TypeReader())->attributes(array_slice($tokens, 1)));
        $tokens = (new MySqlParser())->parse('CREATE TABLE t (c DECIMAL(10,2) ZEROFILL)')->find('type')[0]->tokens();
        self::assertSame(['unsigned' => true, 'zerofill' => true, 'binary' => false, 'octets' => false, 'characterSet' => null], (new TypeReader())->attributes(array_slice($tokens, 1)));
    }

    public function testArgumentsAssignLengthOrPrecisionAndScaleByKind(): void
    {
        $reader = new TypeReader();
        self::assertSame([Builtin::VarChar, 10, null, null], $reader->arguments(Builtin::VarChar, [10]));
        self::assertSame([Builtin::Numeric, null, 10, 2], $reader->arguments(Builtin::Numeric, [10, 2]));
        self::assertSame([Builtin::DoublePrecision, null, 30, null], $reader->arguments(Builtin::Real, [30]));
        self::assertSame([Builtin::Real, null, 24, null], $reader->arguments(Builtin::Real, [24]));
        self::assertSame([Builtin::Time, null, 6, null], $reader->arguments(Builtin::Time, [6]));
        self::assertSame([Builtin::Date, null, null, null], $reader->arguments(Builtin::Date, []));
    }

    public function testIntegerRejectsOverflowingArguments(): void
    {
        $node = new Node('type', 0, []);
        self::assertSame(255, (new TypeReader())->integer([new Token(1, 'NUM', '255', 0)], $node));
        $this->expectException(SemanticException::class);
        (new TypeReader())->integer([new Token(1, 'DECIMAL_NUM', '99999999999999999999999', 0)], $node);
    }
}
