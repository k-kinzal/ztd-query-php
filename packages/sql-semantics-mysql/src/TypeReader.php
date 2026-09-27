<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Ast\Numbers;
use SqlSemantics\Core\Ast\TokenGroups;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDeclaration;
use SqlSemantics\Statement\Declaration\TypeDescriptor;

/**
 * Reads a type declaration by the lexer's type keyword tokens, so every synonym the lexer knows is covered.
 *
 * The keyword token identifies the type; the parenthesized arguments give
 * its length or precision and scale, or its enumeration members; the
 * attributes after them give the sign, zero padding, character set and
 * binary collation facts. A character type declared with the binary
 * character set is the corresponding octet string type.
 *
 * @visibility SqlSemantics
 */
final class TypeReader
{
    private const KINDS = [
        'TINYINT_SYM' => Builtin::TinyInt, 'TINYINT' => Builtin::TinyInt,
        'SMALLINT_SYM' => Builtin::SmallInt, 'SMALLINT' => Builtin::SmallInt,
        'MEDIUMINT_SYM' => Builtin::MediumInt, 'MEDIUMINT' => Builtin::MediumInt,
        'INT_SYM' => Builtin::Integer,
        'BIGINT_SYM' => Builtin::BigInt, 'BIGINT' => Builtin::BigInt,
        'REAL_SYM' => Builtin::DoublePrecision, 'REAL' => Builtin::DoublePrecision,
        'DOUBLE_SYM' => Builtin::DoublePrecision,
        'FLOAT_SYM' => Builtin::Real,
        'DECIMAL_SYM' => Builtin::Numeric, 'NUMERIC_SYM' => Builtin::Numeric, 'FIXED_SYM' => Builtin::Numeric,
        'BIT_SYM' => Builtin::Bit,
        'BOOL_SYM' => Builtin::TinyInt, 'BOOLEAN_SYM' => Builtin::TinyInt,
        'CHAR_SYM' => Builtin::Char, 'NCHAR_SYM' => Builtin::Char, 'NATIONAL_SYM' => Builtin::Char,
        'VARCHAR_SYM' => Builtin::VarChar, 'VARCHAR' => Builtin::VarChar, 'NVARCHAR_SYM' => Builtin::VarChar,
        'BINARY_SYM' => Builtin::Binary, 'BINARY' => Builtin::Binary,
        'VARBINARY_SYM' => Builtin::VarBinary, 'VARBINARY' => Builtin::VarBinary,
        'YEAR_SYM' => Builtin::Year,
        'DATE_SYM' => Builtin::Date,
        'TIME_SYM' => Builtin::Time,
        'TIMESTAMP_SYM' => Builtin::Timestamp, 'TIMESTAMP' => Builtin::Timestamp,
        'DATETIME_SYM' => Builtin::DateTime, 'DATETIME' => Builtin::DateTime,
        'TINYBLOB_SYM' => Builtin::TinyBlob, 'TINYBLOB' => Builtin::TinyBlob,
        'BLOB_SYM' => Builtin::Blob,
        'MEDIUMBLOB_SYM' => Builtin::MediumBlob, 'MEDIUMBLOB' => Builtin::MediumBlob,
        'LONGBLOB_SYM' => Builtin::LongBlob, 'LONGBLOB' => Builtin::LongBlob,
        'LONG_SYM' => Builtin::MediumText,
        'TINYTEXT_SYN' => Builtin::TinyText, 'TINYTEXT' => Builtin::TinyText,
        'TEXT_SYM' => Builtin::Text,
        'MEDIUMTEXT_SYM' => Builtin::MediumText, 'MEDIUMTEXT' => Builtin::MediumText,
        'LONGTEXT_SYM' => Builtin::LongText, 'LONGTEXT' => Builtin::LongText,
        'ENUM_SYM' => Builtin::Enum, 'ENUM' => Builtin::Enum,
        'SET_SYM' => Builtin::Set, 'SET' => Builtin::Set,
        'JSON_SYM' => Builtin::Json,
        'SERIAL_SYM' => Builtin::BigInt,
        'VECTOR_SYM' => Builtin::Vector,
        'GEOMETRY_SYM' => Builtin::Geometry,
        'GEOMETRYCOLLECTION_SYM' => Builtin::GeometryCollection, 'GEOMETRYCOLLECTION' => Builtin::GeometryCollection,
        'POINT_SYM' => Builtin::Point,
        'MULTIPOINT_SYM' => Builtin::MultiPoint, 'MULTIPOINT' => Builtin::MultiPoint,
        'LINESTRING_SYM' => Builtin::LineString, 'LINESTRING' => Builtin::LineString,
        'MULTILINESTRING_SYM' => Builtin::MultiLineString, 'MULTILINESTRING' => Builtin::MultiLineString,
        'POLYGON_SYM' => Builtin::Polygon, 'POLYGON' => Builtin::Polygon,
        'MULTIPOLYGON_SYM' => Builtin::MultiPolygon, 'MULTIPOLYGON' => Builtin::MultiPolygon,
    ];

    private const NAME_TOKENS = ['VARYING', 'VARCHAR_SYM', 'VARCHAR', 'CHAR_SYM', 'VARBINARY_SYM', 'VARBINARY', 'PRECISION'];

    private const OCTETS = [
        'char' => Builtin::Binary,
        'varchar' => Builtin::VarBinary,
        'tinytext' => Builtin::TinyBlob,
        'text' => Builtin::Blob,
        'mediumtext' => Builtin::MediumBlob,
        'longtext' => Builtin::LongBlob,
    ];

    /**
     * Reads one type declaration; SERIAL declares a nonnullable, automatically generated, unique BIGINT UNSIGNED.
     *
     * @throws SemanticException When the declaration is outside the lexer's type vocabulary or its arguments are invalid
     */
    public function read(Node $node, ValueReader $values): TypeDeclaration
    {
        $tokens = $node->tokens();
        if ($tokens === []) {
            Tree::unsupported($node, 'type declaration');
        }
        if ($tokens[0]->name === 'SERIAL_SYM') {
            return new TypeDeclaration(new TypeDescriptor(Builtin::BigInt, unsigned: true), autoIncrement: true, notNull: true, unique: true);
        }
        $leading = $this->leading($tokens);
        $kind = $this->kind($leading, $node);
        $facts = $this->attributes(array_slice($tokens, count($leading)));
        if (in_array($tokens[0]->name, ['NCHAR_SYM', 'NVARCHAR_SYM', 'NATIONAL_SYM'], true)) {
            $facts['characterSet'] = 'utf8mb3';
        }
        $group = TokenGroups::parentheses($tokens)[0] ?? [];
        $members = [];
        if (in_array($kind, [Builtin::Enum, Builtin::Set], true)) {
            $members = array_map($values->read(...), Tree::outer($node, ['text_string']));
            $group = [];
        }
        [$kind, $length, $precision, $scale] = $this->arguments($kind, array_map(fn (array $argument): int => $this->integer($argument, $node), Numbers::arguments($group)));
        if (in_array($tokens[0]->name, ['BOOL_SYM', 'BOOLEAN_SYM'], true)) {
            $length = 1;
        }
        if ($facts['octets'] && $kind->isCharacter()) {
            $kind = self::OCTETS[$kind->value] ?? $kind;
        }
        $character = $kind->isCharacter();

        return new TypeDeclaration(new TypeDescriptor($kind, $length, $precision, $scale, $kind->isNumeric() && $facts['unsigned'], $kind->isNumeric() && $facts['zerofill'], $character && $facts['binary'], $character ? $facts['characterSet'] : null, $members));
    }

    /**
     * Identifies the type from the keyword tokens that spell its name.
     *
     * @param list<Token> $leading
     * @throws SemanticException When the first token is not a type keyword
     */
    public function kind(array $leading, Node $source): Builtin
    {
        $kind = self::KINDS[$leading[0]->name ?? ''] ?? null;
        if ($kind === null) {
            Tree::unsupported($source, 'type declaration');
        }
        $names = array_map(static fn (Token $token): string => $token->name, $leading);
        if ($kind === Builtin::Char && array_intersect($names, ['VARYING', 'VARCHAR_SYM', 'VARCHAR']) !== []) {
            return Builtin::VarChar;
        }
        if ($leading[0]->name === 'LONG_SYM' && array_intersect($names, ['VARBINARY_SYM', 'VARBINARY']) !== []) {
            return Builtin::MediumBlob;
        }

        return $kind;
    }

    /**
     * Selects the keyword tokens that spell the type name, before any argument or attribute.
     *
     * @param list<Token> $tokens
     * @return list<Token>
     */
    public function leading(array $tokens): array
    {
        $leading = array_slice($tokens, 0, 1);
        foreach (array_slice($tokens, 1) as $index => $token) {
            $next = strtoupper($tokens[$index + 2]->text ?? '');
            if (!in_array($token->name, self::NAME_TOKENS, true) || ($token->name === 'CHAR_SYM' && $next === 'SET')) {
                break;
            }
            $leading[] = $token;
        }

        return $leading;
    }

    /**
     * Reads the sign, padding, character set and collation attributes written after the type name.
     *
     * @param list<Token> $tokens
     * @return array{unsigned: bool, zerofill: bool, binary: bool, octets: bool, characterSet: ?string}
     */
    public function attributes(array $tokens): array
    {
        $facts = ['unsigned' => false, 'zerofill' => false, 'binary' => false, 'octets' => false, 'characterSet' => null];
        $depth = 0;
        for ($index = 0; $index < count($tokens); ++$index) {
            $token = $tokens[$index];
            $depth += $token->text === '(' ? 1 : ($token->text === ')' ? -1 : 0);
            if ($depth > 0 || in_array($token->text, ['(', ')'], true)) {
                continue;
            }
            $word = strtoupper($token->text);
            if ($word === 'CHARSET' || (in_array($word, ['CHARACTER', 'CHAR'], true) && strtoupper($tokens[$index + 1]->text ?? '') === 'SET')) {
                $index += $word === 'CHARSET' ? 1 : 2;
                $facts['characterSet'] = strtolower((new NameRules())->name($tokens[$index]));
                $facts['octets'] = $facts['characterSet'] === 'binary';
                continue;
            }
            match ($word) {
                'UNSIGNED' => $facts['unsigned'] = true,
                'ZEROFILL' => $facts['zerofill'] = $facts['unsigned'] = true,
                'SIGNED' => null,
                'BINARY' => $facts['binary'] = true,
                'ASCII' => $facts['characterSet'] = 'latin1',
                'UNICODE' => $facts['characterSet'] = 'ucs2',
                'BYTE' => $facts['octets'] = true,
                default => Tree::unsupported($token, 'type attribute'),
            };
        }

        return $facts;
    }

    /**
     * Assigns the numeric arguments to the slots the type gives them; FLOAT with more than 24 bits of precision is a DOUBLE.
     *
     * @param list<int> $arguments
     * @return array{Builtin, ?int, ?int, ?int} Kind, length, precision and scale
     */
    public function arguments(Builtin $kind, array $arguments): array
    {
        if ($arguments === []) {
            return [$kind, null, null, null];
        }
        if (in_array($kind, [Builtin::Numeric, Builtin::Real, Builtin::DoublePrecision], true)) {
            if ($kind === Builtin::Real && count($arguments) === 1 && $arguments[0] > 24) {
                $kind = Builtin::DoublePrecision;
            }
            return [$kind, null, $arguments[0], $arguments[1] ?? null];
        }
        if ($kind->isTemporal()) {
            return [$kind, null, $arguments[0], null];
        }

        return [$kind, $arguments[0], null, null];
    }

    /**
     * @param list<Token> $tokens
     * @throws SemanticException When an argument is not an integer the database can store
     */
    public function integer(array $tokens, Node $source): int
    {
        $value = Numbers::integer($tokens);
        if ($value === null || $value < 0) {
            throw new SemanticException('invalid-type-modifier', 'Invalid type argument: ' . Tree::text($source), $source);
        }

        return $value;
    }
}
