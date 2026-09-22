<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
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
        $generic = Tree::outer($source, ['GenericType'])[0] ?? null;
        if ($this->types->dialect === Dialect::PostgreSql && $generic !== null) {
            $name = Tree::child($generic, ['type_function_name']);
            $spelling = $name === null ? '' : strtoupper(Tree::text($name));
            if (Tree::child($generic, ['attrs']) !== null || $this->types->canonical($spelling) === null && Identity\BuiltinIdentity::tryFrom(strtolower($spelling)) === null) {
                return $this->named($generic);
            }
        }
        $parts = TypeWords::read($source);
        $name = strtoupper(implode(' ', $parts->words));
        $canonical = $this->types->canonical($name) ?? strtolower($name);
        if (str_starts_with($name, 'INTERVAL')) {
            $fields = trim(substr($name, 8));
            return new Identity\IntervalStorage(Identity\IntervalFields::from($fields), $parts->parameters[0] ?? null);
        }
        if ($this->types->dialect === Dialect::PostgreSql && ($generic = Tree::outer($source, ['GenericType'])[0] ?? null) !== null && Identity\BuiltinIdentity::tryFrom($canonical) === null) {
            return $this->named($generic);
        }
        if (in_array($name, ['ENUM', 'SET'], true)) {
            return LabelReader::read($source, $name);
        }
        $baseName = preg_replace('/ (WITH|WITHOUT) TIME ZONE$/i', '', $canonical) ?? $canonical;
        $base = Identity\BuiltinIdentity::tryFrom($baseName);
        if ($base === null) {
            throw new UnclassifiedSql('Unclassified type declaration: ' . $source->toString());
        }
        return TypeFamilies::make($base, $parts, $this->types->dialect, $source);
    }

    /**
     * Preserves the name and typed modifier expressions of a user-defined type.
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
        $arguments = [];
        $scope = new Scope($identifiers);
        foreach (Tree::outer($source, ['a_expr']) as $expression) {
            $arguments[] = (new ExpressionBinder())->bind($expression, $scope);
        }
        return new Identity\NamedIdentity(new QualifiedName($parts), $arguments);
    }
}
