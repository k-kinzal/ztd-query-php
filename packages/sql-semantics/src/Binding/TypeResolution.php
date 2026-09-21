<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Resolves the supported built-in common types; unknown coercions are rejected.
 *
 * @visibility SqlSemantics
 */
final class TypeResolution
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Dialect $dialect)
    {
    }

    /**
     * @param list<Expression> $expressions
     * @throws SemanticException
     */
    public function common(array $expressions, Node|Token $source): TypeDescriptor
    {
        $types = [];
        foreach ($expressions as $expression) {
            if ($expression->type->name !== 'unknown') {
                $types[] = $expression->type;
            }
        }
        if ($types === []) {
            return new TypeDescriptor($this->dialect, $this->dialect === Dialect::PostgreSql ? 'text' : 'unknown');
        }
        $names = array_values(array_unique(array_map(static fn (TypeDescriptor $type): string => $type->name, $types)));
        if (count($names) === 1) {
            return new TypeDescriptor($this->dialect, $types[0]->name, affinity: $types[0]->affinity);
        }
        if ($this->dialect === Dialect::Sqlite) {
            return new TypeDescriptor($this->dialect, 'dynamic');
        }
        $numeric = ['smallint', 'integer', 'bigint', 'numeric', 'real', 'double precision'];
        if (array_diff($names, $numeric) === []) {
            $rank = 0;
            foreach ($numeric as $index => $name) {
                if (in_array($name, $names, true)) {
                    $rank = $index;
                }
            }
            return new TypeDescriptor($this->dialect, $numeric[$rank]);
        }
        if (array_diff($names, ['varchar', 'text', 'char']) === []) {
            return new TypeDescriptor($this->dialect, 'text');
        }

        throw new SemanticException('unsupported-coercion', 'Cannot establish a common type for: ' . implode(', ', $names), $source);
    }

    /**
     * Returns the dialect result type of a predicate.
     */
    public function boolean(): TypeDescriptor
    {
        return new TypeDescriptor($this->dialect, $this->dialect === Dialect::PostgreSql ? 'boolean' : 'integer');
    }
}
