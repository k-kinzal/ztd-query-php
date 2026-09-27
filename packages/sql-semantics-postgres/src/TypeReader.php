<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Numbers;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\IntervalFields;
use SqlSemantics\Statement\Declaration\TypeDeclaration;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Declaration\TypeName;

/**
 * Reads a type name by the grammar production that built it: keyword types by their tokens, generic names by the catalog.
 *
 * Array bounds become array dimensions, time zone and interval qualifiers
 * become separate facts, and the serial names declare an integer that is
 * generated automatically and never NULL. A generic name outside the
 * catalog is kept as a named type without interpreting its modifiers.
 *
 * @visibility SqlSemantics
 */
final class TypeReader
{
    private const CATALOG = [
        'int2' => Builtin::SmallInt, 'int4' => Builtin::Integer, 'int8' => Builtin::BigInt,
        'numeric' => Builtin::Numeric, 'float4' => Builtin::Real, 'float8' => Builtin::DoublePrecision,
        'money' => Builtin::Money, 'bool' => Builtin::Boolean, 'bit' => Builtin::Bit, 'varbit' => Builtin::BitVarying,
        'bpchar' => Builtin::Char, 'varchar' => Builtin::VarChar, 'text' => Builtin::Text, 'char' => Builtin::QuotedChar, 'name' => Builtin::Name,
        'bytea' => Builtin::Bytea, 'date' => Builtin::Date, 'time' => Builtin::Time, 'timetz' => Builtin::TimeTz,
        'timestamp' => Builtin::Timestamp, 'timestamptz' => Builtin::TimestampTz, 'interval' => Builtin::Interval,
        'json' => Builtin::Json, 'jsonb' => Builtin::Jsonb, 'jsonpath' => Builtin::JsonPath, 'xml' => Builtin::Xml, 'uuid' => Builtin::Uuid,
        'point' => Builtin::Point, 'line' => Builtin::Line, 'lseg' => Builtin::LineSegment, 'box' => Builtin::Box,
        'path' => Builtin::Path, 'polygon' => Builtin::Polygon, 'circle' => Builtin::Circle,
        'inet' => Builtin::Inet, 'cidr' => Builtin::Cidr, 'macaddr' => Builtin::MacAddr, 'macaddr8' => Builtin::MacAddr8,
        'tsvector' => Builtin::TsVector, 'tsquery' => Builtin::TsQuery,
        'int4range' => Builtin::Int4Range, 'int8range' => Builtin::Int8Range, 'numrange' => Builtin::NumRange,
        'tsrange' => Builtin::TsRange, 'tstzrange' => Builtin::TsTzRange, 'daterange' => Builtin::DateRange,
        'int4multirange' => Builtin::Int4MultiRange, 'int8multirange' => Builtin::Int8MultiRange, 'nummultirange' => Builtin::NumMultiRange,
        'tsmultirange' => Builtin::TsMultiRange, 'tstzmultirange' => Builtin::TsTzMultiRange, 'datemultirange' => Builtin::DateMultiRange,
        'oid' => Builtin::Oid, 'regclass' => Builtin::RegClass, 'regcollation' => Builtin::RegCollation, 'regconfig' => Builtin::RegConfig,
        'regdictionary' => Builtin::RegDictionary, 'regnamespace' => Builtin::RegNamespace, 'regoper' => Builtin::RegOper,
        'regoperator' => Builtin::RegOperator, 'regproc' => Builtin::RegProc, 'regprocedure' => Builtin::RegProcedure,
        'regrole' => Builtin::RegRole, 'regtype' => Builtin::RegType,
        'pg_lsn' => Builtin::PgLsn, 'pg_snapshot' => Builtin::PgSnapshot, 'txid_snapshot' => Builtin::TxidSnapshot,
    ];

    private const SERIALS = [
        'smallserial' => Builtin::SmallInt, 'serial2' => Builtin::SmallInt,
        'serial' => Builtin::Integer, 'serial4' => Builtin::Integer,
        'bigserial' => Builtin::BigInt, 'serial8' => Builtin::BigInt,
    ];

    /**
     * Reads one Typename; a set-returning type is not a column type.
     *
     * A declaration the database rejects, such as a SETOF column or a type
     * modifier that is not a constant, is invalid rather than unsupported, so
     * it is an error and never an unreadable declaration.
     *
     * @throws SemanticException When the declaration is outside the modeled surface or invalid
     */
    public function read(Node $node): TypeDeclaration
    {
        if (strtoupper($node->tokens()[0]->text ?? '') === 'SETOF') {
            throw new SemanticException('invalid-column-type', 'A column cannot be declared SETOF: ' . Tree::text($node), $node);
        }
        $simple = Tree::outer($node, ['SimpleTypename'])[0] ?? null;
        $inner = $simple === null ? null : (Tree::significant($simple)[0] ?? null);
        if (!$inner instanceof Node) {
            Tree::unsupported($node, 'column type');
        }
        $facts = ['length' => null, 'precision' => null, 'scale' => null, 'fields' => null, 'autoIncrement' => false];
        $name = match ($inner->name) {
            'Numeric' => $this->numeric($inner, $facts),
            'Bit' => $this->bit($inner, $facts),
            'Character' => $this->character($inner, $facts),
            'ConstDatetime' => $this->datetime($inner, $facts),
            'ConstInterval' => $this->interval($simple, $facts),
            'JsonType' => Builtin::Json,
            'GenericType' => $this->generic($inner, $facts),
            default => Tree::unsupported($node, 'column type'),
        };
        $type = new TypeDescriptor($name, $facts['length'], $facts['precision'], $facts['scale'], arrayDimensions: $this->dimensions($node), intervalFields: $facts['fields']);

        return new TypeDeclaration($type, autoIncrement: $facts['autoIncrement'], notNull: $facts['autoIncrement']);
    }

    /**
     * Counts declared array dimensions; the word ARRAY without brackets declares one.
     */
    public function dimensions(Node $node): int
    {
        $brackets = 0;
        $word = false;
        foreach ($node->children as $child) {
            $brackets += $child instanceof Node && $child->name === 'opt_array_bounds' ? count(array_filter($child->tokens(), static fn (Token $token): bool => $token->text === '[')) : 0;
            $brackets += $child instanceof Token && $child->text === '[' ? 1 : 0;
            $word = $word || ($child instanceof Token && strtoupper($child->text) === 'ARRAY');
        }

        return $brackets > 0 ? $brackets : ($word ? 1 : 0);
    }

    /**
     * @param array{length: ?int, precision: ?int, scale: ?int, fields: ?IntervalFields, autoIncrement: bool} $facts
     */
    public function numeric(Node $node, array &$facts): Builtin
    {
        $tokens = $node->tokens();
        $kind = match ($tokens[0]->name) {
            'INT_P', 'INTEGER' => Builtin::Integer,
            'SMALLINT' => Builtin::SmallInt,
            'BIGINT' => Builtin::BigInt,
            'REAL' => Builtin::Real,
            'FLOAT_P' => Builtin::DoublePrecision,
            'DOUBLE_P' => Builtin::DoublePrecision,
            'DECIMAL_P', 'DEC', 'NUMERIC' => Builtin::Numeric,
            'BOOLEAN_P' => Builtin::Boolean,
            default => Tree::unsupported($node, 'numeric type'),
        };
        if ($tokens[0]->name === 'FLOAT_P') {
            $facts['precision'] = $this->iconst($node);
            $kind = $facts['precision'] !== null && $facts['precision'] <= 24 ? Builtin::Real : Builtin::DoublePrecision;
        }
        if ($kind === Builtin::Numeric) {
            [$facts['precision'], $facts['scale']] = $this->modifiers($node, 2, $node) + [null, null];
        }

        return $kind;
    }

    /**
     * @param array{length: ?int, precision: ?int, scale: ?int, fields: ?IntervalFields, autoIncrement: bool} $facts
     */
    public function bit(Node $node, array &$facts): Builtin
    {
        $facts['length'] = $this->modifiers($node, 1, $node)[0] ?? null;

        return $this->varying($node) ? Builtin::BitVarying : Builtin::Bit;
    }

    /**
     * @param array{length: ?int, precision: ?int, scale: ?int, fields: ?IntervalFields, autoIncrement: bool} $facts
     */
    public function character(Node $node, array &$facts): Builtin
    {
        $facts['length'] = $this->iconst($node);

        return $this->varying($node) ? Builtin::VarChar : Builtin::Char;
    }

    /**
     * @param array{length: ?int, precision: ?int, scale: ?int, fields: ?IntervalFields, autoIncrement: bool} $facts
     */
    public function datetime(Node $node, array &$facts): Builtin
    {
        $facts['precision'] = $this->iconst($node);
        $timestamp = $node->tokens()[0]->name === 'TIMESTAMP';
        $zone = Tree::child($node, ['opt_timezone']);
        $withZone = $zone !== null && strtoupper($zone->tokens()[0]->text) === 'WITH';

        return match (true) {
            $timestamp && $withZone => Builtin::TimestampTz,
            $timestamp => Builtin::Timestamp,
            $withZone => Builtin::TimeTz,
            default => Builtin::Time,
        };
    }

    /**
     * @param array{length: ?int, precision: ?int, scale: ?int, fields: ?IntervalFields, autoIncrement: bool} $facts
     */
    public function interval(Node $simple, array &$facts): Builtin
    {
        $facts['precision'] = $this->iconst($simple);
        $words = [];
        foreach ((Tree::child($simple, ['opt_interval'])?->tokens()) ?? [] as $token) {
            if (ctype_alpha($token->text) && strtoupper($token->text) !== 'TO') {
                $words[] = strtolower($token->text);
            }
        }
        $facts['fields'] = $words === [] ? null : IntervalFields::from(implode(' to ', $words));

        return Builtin::Interval;
    }

    /**
     * @param array{length: ?int, precision: ?int, scale: ?int, fields: ?IntervalFields, autoIncrement: bool} $facts
     */
    public function generic(Node $node, array &$facts): Builtin|TypeName
    {
        $parts = [];
        foreach ($node->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['type_function_name', 'attrs'], true)) {
                foreach ($child->tokens() as $token) {
                    if ($token->text !== '.') {
                        $parts[] = (new NameRules())->name($token);
                    }
                }
            }
        }
        $catalog = count($parts) === 1 || (count($parts) === 2 && $parts[0] === 'pg_catalog') ? $parts[count($parts) - 1] : null;
        if ($catalog !== null && isset(self::SERIALS[$catalog])) {
            $facts['autoIncrement'] = true;

            return self::SERIALS[$catalog];
        }
        $kind = $catalog === null ? null : (self::CATALOG[$catalog] ?? null);
        if ($kind === null) {
            if ($parts === []) {
                Tree::unsupported($node, 'type name');
            }

            return new TypeName($parts);
        }
        $this->assign($kind, $this->modifiers($node, $kind === Builtin::Numeric ? 2 : 1, $node), $facts);

        return $kind;
    }

    /**
     * Places catalog type modifiers in the slot the type gives them: precision and scale, seconds precision, or length.
     *
     * @param list<int> $modifiers
     * @param array{length: ?int, precision: ?int, scale: ?int, fields: ?IntervalFields, autoIncrement: bool} $facts
     */
    public function assign(Builtin $kind, array $modifiers, array &$facts): void
    {
        if ($kind === Builtin::Numeric) {
            [$facts['precision'], $facts['scale']] = $modifiers + [null, null];
        } elseif ($kind->isTemporal()) {
            $facts['precision'] = $modifiers[0] ?? null;
        } elseif ($kind->isCharacter() || in_array($kind, [Builtin::Bit, Builtin::BitVarying], true)) {
            $facts['length'] = $modifiers[0] ?? null;
        }
    }

    /**
     * Reports whether a character or bit type is declared with varying length.
     */
    public function varying(Node $node): bool
    {
        foreach ($node->tokens() as $token) {
            if (in_array($token->name, ['VARYING', 'VARCHAR'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reads the single integer constant of a keyword type, which the grammar guarantees is nonnegative.
     */
    public function iconst(Node $node): ?int
    {
        $constant = Tree::outer($node, ['Iconst'])[0] ?? null;

        return $constant === null ? null : $this->integer($constant->tokens(), $constant);
    }

    /**
     * Reads the integer constants of a modifier list; other modifier forms are not modeled.
     *
     * @return list<int>
     * @throws SemanticException When a modifier is not an integer constant or there are too many
     */
    public function modifiers(Node $node, int $limit, Node $source): array
    {
        $list = Tree::outer($node, ['expr_list'])[0] ?? null;
        $expressions = $list === null ? [] : Tree::outer($list, ['a_expr']);
        if (count($expressions) > $limit) {
            throw new SemanticException('invalid-type-modifier', 'Too many type modifiers: ' . Tree::text($source), $source);
        }
        $values = [];
        foreach ($expressions as $index => $expression) {
            $tokens = $expression->tokens();
            $value = Numbers::integer($tokens);
            if ($value === null && count($tokens) === 1 && $tokens[0]->name === 'SCONST') {
                Tree::unsupported($expression, 'type modifier');
            }
            if ($value === null) {
                throw new SemanticException('invalid-type-modifier', 'Type modifiers must be constants: ' . Tree::text($source), $source);
            }
            if ($value < 0 && $index === 0) {
                throw new SemanticException('invalid-type-modifier', 'Invalid type modifier: ' . Tree::text($source), $source);
            }
            $values[] = $value;
        }

        return $values;
    }

    /**
     * @param list<Token> $tokens
     * @throws SemanticException When the constant overflows a 64-bit integer
     */
    public function integer(array $tokens, Node $source): int
    {
        $value = Numbers::integer($tokens);
        if ($value === null) {
            throw new SemanticException('invalid-type-modifier', 'Invalid type modifier: ' . Tree::text($source), $source);
        }

        return $value;
    }
}
