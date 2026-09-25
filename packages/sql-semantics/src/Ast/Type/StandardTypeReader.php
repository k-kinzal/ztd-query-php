<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Type\Identity;

/**
 * Classifies built-in families and PostgreSQL named types with their own operands.
 * @visibility SqlSemantics
 */
final class StandardTypeReader
{
    /**
     * The PostgreSQL catalog type names the model knows as built-in types; the server finds them under a quoted name
     * or in pg_catalog as it finds their unquoted spelling, while the SQL keyword spellings (integer, boolean, ...)
     * are not type names once quoted.
     */
    public const CATALOG_TYPES = ['int2', 'int4', 'int8', 'float4', 'float8', 'numeric', 'bool', 'text', 'varchar', 'bpchar', 'date', 'time', 'timetz', 'timestamp', 'timestamptz', 'interval', 'bit', 'varbit', 'bytea', 'json', 'jsonb', 'uuid', 'xml'];

    /**
     * Shares built-in alias resolution with the declaration reader.
     */
    public function __construct(public readonly TypeReader $types)
    {
    }

    /**
     * @throws UnclassifiedSql
     */
    public function read(Node $source): Identity\TypeIdentity
    {
        if ($this->types->dialect === Dialect::PostgreSql) {
            ModifierReader::validate($source);
        }
        $generic = Tree::outer($source, ['GenericType'])[0] ?? null;
        $catalog = $this->types->dialect === Dialect::PostgreSql && $generic !== null ? self::catalogName($generic) : null;
        $userType = $catalog === null ? $this->userType($generic) : null;
        if ($userType !== null) {
            return $userType;
        }
        $parts = TypeWords::read($source, $this->types->dialect);
        $parts = $catalog === null ? $parts : new TypeWords([strtoupper($catalog)], $parts->parameters, false, null, false);
        $name = strtoupper(implode(' ', $parts->words));
        $canonical = $this->types->canonical($name) ?? strtolower($name);
        if (str_starts_with($name, 'INTERVAL')) {
            $fields = trim(substr($name, 8));
            return new Identity\IntervalStorage(Identity\IntervalFields::from($fields), ParameterDomains::number($parts->parameters[0] ?? null, $source));
        }
        if ($this->types->dialect === Dialect::PostgreSql && ($generic = Tree::outer($source, ['GenericType'])[0] ?? null) !== null && Identity\BuiltinIdentity::tryFrom($canonical) === null) {
            return $this->named($generic);
        }
        if (in_array($name, ['ENUM', 'SET'], true)) {
            return LabelReader::read($source, $name);
        }
        $baseName = preg_replace('/ (WITH|WITHOUT) TIME ZONE$/iD', '', $canonical) ?? $canonical;
        $base = Identity\BuiltinIdentity::tryFrom($baseName);
        if ($base === null) {
            throw new UnclassifiedSql('Unclassified type declaration: ' . $source->toString());
        }
        return TypeFamilies::make($base, $parts, $this->types->dialect, $source);
    }

    /**
     * Returns the named type a PostgreSQL generic type name declares: a qualified name, or a name that is no built-in
     * spelling; null for another dialect, another type syntax, or a built-in name.
     */
    public function userType(?Node $generic): ?Identity\NamedIdentity
    {
        if ($this->types->dialect !== Dialect::PostgreSql || $generic === null) {
            return null;
        }
        $name = Tree::child($generic, ['type_function_name']);
        $spelling = $name === null ? '' : strtoupper(Tree::text($name));
        return Tree::child($generic, ['attrs']) !== null || $this->types->canonical($spelling) === null && Identity\BuiltinIdentity::tryFrom(strtolower($spelling)) === null ? $this->named($generic) : null;
    }

    /**
     * Returns the built-in catalog type a PostgreSQL generic type name reaches, written unqualified or in pg_catalog,
     * quoted or not; null for any other name.
     */
    public static function catalogName(Node $generic): ?string
    {
        $identifiers = new Identifiers(Dialect::PostgreSql);
        $name = Tree::child($generic, ['type_function_name']);
        if ($name === null) {
            return null;
        }
        $parts = $identifiers->parts($name);
        foreach (Tree::outer($generic, ['attr_name']) as $attribute) {
            array_push($parts, ...$identifiers->parts($attribute));
        }
        $type = match (count($parts)) {
            1 => $parts[0],
            2 => $parts[0] === 'pg_catalog' ? $parts[1] : null,
            default => null,
        };
        return in_array($type, self::CATALOG_TYPES, true) ? $type : null;
    }

    /**
     * Preserves the name and classified type-input operands of a user-defined type.
     */
    public function named(Node $source): Identity\NamedIdentity
    {
        $identifiers = new Identifiers(Dialect::PostgreSql);
        $name = Tree::child($source, ['type_function_name']);
        if ($name === null) {
            Tree::invalid($source, 'type reference');
        }
        $parts = $identifiers->parts($name);
        foreach (Tree::outer($source, ['attr_name']) as $attribute) {
            array_push($parts, ...$identifiers->parts($attribute));
        }
        $arguments = ModifierBinder::parameters($source);
        return new Identity\NamedIdentity(new QualifiedName($parts), $arguments);
    }
}
