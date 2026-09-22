<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Dialect;
use SqlSemantics\Type\Identity;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Classifies type declarations by dialect and parameter ownership.
 * @visibility SqlSemantics
 */
final class DeclarationReader
{
    /**
     * Shares the dialect's built-in aliases and affinity rules.
     */
    public function __construct(public readonly TypeReader $types)
    {
    }

    /**
     * Returns the concrete type identity without storing a syntax payload.
     */
    public function read(Node $source): TypeDescriptor
    {
        if ($this->types->dialect === Dialect::Sqlite) {
            return $this->sqlite($source);
        }
        $simple = Tree::child($source, ['SimpleTypename']);
        $bounds = Tree::child($source, ['opt_array_bounds']);
        if ($simple !== null && ($bounds !== null || in_array('ARRAY', array_map(static fn ($token): string => strtoupper($token->text), $source->tokens()), true))) {
            $dimensions = ArrayBounds::read($source, $bounds);
            return new TypeDescriptor($this->types->dialect, new Identity\ArrayStorage($this->read($simple), $dimensions));
        }
        return new TypeDescriptor($this->types->dialect, (new StandardTypeReader($this->types))->read($simple ?? $source));
    }

    /**
     * Preserves SQLite's declared type identity and two optional numeric parameters.
     */
    public function sqlite(Node $source): TypeDescriptor
    {
        $typename = Tree::child($source, ['typename']) ?? $source;
        $tokens = $typename->tokens();
        $words = array_map(static fn ($token): string => strtoupper($token->text), $tokens);
        if (array_slice($words, -2) === ['GENERATED', 'ALWAYS']) {
            $tokens = array_slice($tokens, 0, -2);
        }
        $identifiers = new Identifiers(Dialect::Sqlite);
        $name = strtolower(implode(' ', array_map($identifiers->name(...), $tokens)));
        $parameters = [];
        foreach (Tree::outer($source, ['signed']) as $parameter) {
            $parameters[] = new Identity\Numeric\NumericParameter(implode('', array_map(static fn ($token): string => $token->text, $parameter->tokens())));
        }
        return new TypeDescriptor(Dialect::Sqlite, new Identity\SqliteDeclaration($name, Identity\StorageAffinity::from($this->types->affinity(strtoupper($name))), $parameters[0] ?? null, $parameters[1] ?? null));
    }
}
