<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\ColumnDefinition;
use SqlSemantics\Ast\Declaration\TableConstraint;
use SqlSemantics\Dialect;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Reads declaration-level nullability, defaults, and column constraints.
 *
 * @visibility SqlSemantics
 */
final class ColumnReader
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Identifiers $identifiers)
    {
    }

    /**
     * @param list<Node> $attributes
     * @return array{ColumnDefinition, list<TableConstraint>}
     * @throws \SqlSemantics\InvalidSql
     */
    public function read(Node $node, array $attributes): array
    {
        $nameNode = Tree::outer($node, ['ColId', 'ident', 'nm', 'field_ident'])[0] ?? null;
        $typeNode = Tree::outer($node, ['Typename', 'type', 'typetoken'])[0] ?? null;
        if ($nameNode === null) {
            Tree::invalid($node, 'column declaration');
        }
        $name = $this->identifiers->parts($nameNode)[0];
        $serial = $typeNode === null || $this->identifiers->dialect !== Dialect::PostgreSql ? null : $this->serial($typeNode);
        $type = $this->type($typeNode, $serial);
        $counter = $this->identifiers->dialect === Dialect::MySql && $type->identity === \SqlSemantics\Type\Identity\BuiltinIdentity::Serial;
        $type = $counter ? new TypeDescriptor(Dialect::MySql, new \SqlSemantics\Type\Identity\Numeric\IntegerStorage(\SqlSemantics\Type\Identity\BuiltinIdentity::BigInt, null, true)) : $type;
        $groups = (new ConstraintGroups())->read($attributes);
        [$constraints, $generated] = $this->constraints($groups, $name, self::generatedExpression($node));
        $default = Definition\ValueSources::default($this->identifiers->dialect, $groups, self::generatedExpression($node) !== null, $counter);
        $nullability = self::nullability($type, $groups, $serial !== null || $counter);
        $options = [...Definition\OptionReader::column($node, $attributes, $this->identifiers), ...($serial === null ? [] : ['serial' => true]), ...($counter ? ['auto_increment' => true, 'serial_type' => true] : [])];
        if ($counter && $typeNode !== null) {
            $constraints[] = new TableConstraint(\SqlSemantics\Schema\ConstraintKind::Unique, [$name], $typeNode);
        }

        return [new ColumnDefinition($name, $type, $nullability, $node, $default, $attributes, $generated, $options), $this->identifiers->dialect === Dialect::MySql ? self::columnKeys($constraints) : $constraints];
    }

    /**
     * Reads the constraints the column attributes write and the generation expression, which a generation attribute
     * gives when the type is not followed by one.
     *
     * @param list<Node> $groups Column attributes grouped with their constraint names, in SQL order
     * @return array{list<TableConstraint>, ?Node}
     */
    public function constraints(array $groups, string $name, ?Node $generated): array
    {
        $constraints = [];
        foreach ($groups as $attribute) {
            $constraint = (new ConstraintReader($this->identifiers))->read($attribute, $name);
            if ($constraint !== null) {
                $constraints[] = $constraint;
                continue;
            }
            $words = self::attributeWords($attribute);
            if (($words[0] ?? '') !== 'DEFAULT' && (in_array('GENERATED', $words, true) || ($words[0] ?? '') === 'AS')) {
                $generated = Tree::outer($attribute, ['a_expr', 'expr'])[0] ?? $attribute;
            }
        }
        return [$constraints, $generated];
    }

    /**
     * Reads the declared type: the integer type of a PostgreSQL serial type, SQLite's typeless declaration, or the
     * written type.
     */
    public function type(?Node $typeNode, ?\SqlSemantics\Type\Identity\BuiltinIdentity $serial): TypeDescriptor
    {
        return match (true) {
            $serial !== null => new TypeDescriptor(Dialect::PostgreSql, new \SqlSemantics\Type\Identity\Numeric\IntegerStorage($serial)),
            $typeNode === null => new TypeDescriptor($this->identifiers->dialect, new \SqlSemantics\Type\Identity\SqliteDeclaration('', \SqlSemantics\Type\Identity\StorageAffinity::Blob)),
            default => (new TypeReader($this->identifiers->dialect))->read($typeNode),
        };
    }

    /**
     * Keeps one primary key and one unique key among the keys a MySQL column declaration writes, the first of each: the
     * server records PRIMARY KEY, UNIQUE, SERIAL DEFAULT VALUE and the SERIAL type as flags of the column and creates
     * one key for each flag set.
     *
     * @param list<TableConstraint> $constraints Constraints of one column declaration in SQL order
     * @return list<TableConstraint>
     */
    public static function columnKeys(array $constraints): array
    {
        $seen = [];
        $result = [];
        foreach ($constraints as $constraint) {
            $key = in_array($constraint->kind, [\SqlSemantics\Schema\ConstraintKind::PrimaryKey, \SqlSemantics\Schema\ConstraintKind::Unique], true) ? $constraint->kind->name : null;
            if ($key !== null && isset($seen[$key])) {
                continue;
            }
            if ($key !== null) {
                $seen[$key] = true;
            }
            $result[] = $constraint;
        }
        return $result;
    }

    /**
     * Returns the integer type a PostgreSQL serial type name stands for: smallserial and serial2 for smallint, serial
     * and serial4 for integer, bigserial and serial8 for bigint; null for any other type. As on the server, only an
     * unqualified name without modifiers or array bounds declares a serial column, quoted or not.
     */
    public function serial(Node $type): ?\SqlSemantics\Type\Identity\BuiltinIdentity
    {
        $generic = Tree::outer($type, ['GenericType'])[0] ?? null;
        $name = $generic === null ? null : Tree::child($generic, ['type_function_name']);
        if ($generic === null || $name === null || array_filter(Tree::outer($type, ['attrs', 'opt_type_modifiers', 'opt_array_bounds']), Tree::hasTokens(...)) !== [] || array_filter($type->tokens(), static fn (\SqlParser\Lexer\Token $token): bool => in_array(strtoupper($token->text), ['ARRAY', 'SETOF', '%'], true)) !== []) {
            return null;
        }
        return match ($this->identifiers->parts($name)[0]) {
            'smallserial', 'serial2' => \SqlSemantics\Type\Identity\BuiltinIdentity::SmallInt,
            'serial', 'serial4' => \SqlSemantics\Type\Identity\BuiltinIdentity::Integer,
            'bigserial', 'serial8' => \SqlSemantics\Type\Identity\BuiltinIdentity::BigInt,
            default => null,
        };
    }

    /**
     * Returns the nullability the declaration's NULL and NOT NULL writing gives the column, as each server decides it:
     * MySQL takes the last of NULL and the attributes that declare NOT NULL (NOT NULL, AUTO_INCREMENT, SERIAL DEFAULT
     * VALUE, and the SERIAL type before any attribute), SQLite ignores NULL so any NOT NULL decides, and PostgreSQL
     * rejects NULL written beside NOT NULL, an identity, or a serial type.
     *
     * @param list<Node> $attributes Column attributes in SQL order
     * @param bool $serial Whether a PostgreSQL serial type or the MySQL SERIAL type declared the column
     * @throws \SqlSemantics\InvalidSql
     */
    public static function nullability(TypeDescriptor $type, array $attributes, bool $serial = false): Nullability
    {
        $dialect = $type->dialect;
        $notNull = $serial;
        $declaredNull = null;
        $declaredNotNull = $serial;
        foreach ($attributes as $attribute) {
            $words = self::attributeWords($attribute);
            if ($words === ['NULL']) {
                $declaredNull ??= $attribute;
                $notNull = $dialect === Dialect::Sqlite && $notNull;
            } elseif (array_slice($words, 0, 2) === ['NOT', 'NULL'] || in_array('IDENTITY', $words, true) || in_array($words, [['AUTO_INCREMENT'], ['SERIAL', 'DEFAULT', 'VALUE']], true)) {
                $notNull = true;
                $declaredNotNull = true;
            }
        }
        if ($dialect === Dialect::PostgreSql && $declaredNull !== null && $declaredNotNull) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::ConflictingNullability, $declaredNull);
        }
        return $notNull ? Nullability::NotNull : Nullability::MaybeNull;
    }

    /**
     * Finds the MySQL generation expression written after the column type (`AS (expression)`), in both the 5.7 and the 8.x shapes.
     */
    public static function generatedExpression(Node $column): ?Node
    {
        $field = Tree::child(Tree::child($column, ['field_spec']) ?? $column, ['field_def']);
        return $field === null ? null : Tree::child(Tree::child($field, ['generated_column_func']) ?? $field, ['expr']);
    }

    /**
     * Returns the uppercase words of a column attribute outside its expressions and without a leading CONSTRAINT name.
     * @return list<string>
     */
    public static function attributeWords(Node $attribute): array
    {
        $words = Tree::keywords($attribute);
        return ($words[0] ?? '') === 'CONSTRAINT' ? array_slice($words, 2) : $words;
    }
}
