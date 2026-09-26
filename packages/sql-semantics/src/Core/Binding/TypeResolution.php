<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Binding;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\TypeDescriptor;

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
        return $this->dialect->platform()->types()->common($expressions, $source);
    }

    /**
     * Returns the dialect result type of a predicate.
     */
    public function boolean(): TypeDescriptor
    {
        return $this->dialect->platform()->types()->boolean();
    }
}
