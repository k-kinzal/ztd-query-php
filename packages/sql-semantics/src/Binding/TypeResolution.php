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
 * Resolves built-in common types while preserving uncertainty about externally defined types.
 *
 * @visibility SqlSemantics
 */
final class TypeResolution
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Dialect $dialect, public readonly Analysis\Diagnostics $diagnostics = new Analysis\Diagnostics())
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
            if ($expression->type->name === 'unknown' && !in_array($expression->kind, [\SqlSemantics\Model\ExpressionKind::Literal, \SqlSemantics\Model\ExpressionKind::Parameter], true)) {
                return TypeDescriptor::builtin($this->dialect, 'unknown');
            }
            if ($expression->type->name !== 'unknown') {
                $types[] = $expression->type;
            }
        }
        $type = \SqlSemantics\Type\CommonStorage::resolve($this->dialect, $types);
        $names = array_values(array_unique(array_map(static fn (TypeDescriptor $type): string => $type->name, $types)));
        $numeric = ['smallint', 'integer', 'bigint', 'numeric', 'real', 'double precision'];
        if ($type->name !== 'unknown') {
            return $type;
        }
        if ($this->dialect === Dialect::PostgreSql && array_diff($names, [...$numeric, 'text', 'varchar', 'char', 'bpchar', 'boolean']) === []) {
            $this->diagnostics->report('incompatible-types', 'Cannot establish a common type for: ' . implode(', ', $names), $source);
        }
        return TypeDescriptor::builtin($this->dialect, 'unknown');
    }

    /**
     * Returns the dialect result type of a predicate.
     */
    public function boolean(): TypeDescriptor
    {
        return TypeDescriptor::builtin($this->dialect, $this->dialect === Dialect::PostgreSql ? 'boolean' : 'integer');
    }
}
