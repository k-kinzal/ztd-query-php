<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * Supplies scalar type interpretation and coercion.
 *
 * @visibility SqlSemantics
 */
interface TypeRules
{
    /**
     * Reads the declared built-in type and preserves its modifiers.
     */
    public function read(Node $node): TypeDescriptor;

    /**
     * Resolves the built-in aliases modeled for this dialect.
     */
    public function canonical(string $name): ?string;

    /**
     * Computes storage affinity in the documented precedence order.
     */
    public function affinity(string $name): string;

    /**
     * Classifies a literal's lexical category without converting its contents.
     */
    public function typeName(Token $token): ?string;

    /**
     * Chooses an integer width from its decimal spelling.
     */
    public function integer(string $text): string;

    /**
     * @param list<Expression> $expressions
     * @throws SemanticException
     */
    public function common(array $expressions, Node|Token $source): TypeDescriptor;

    /**
     * Returns the dialect result type of a predicate.
     */
    public function boolean(): TypeDescriptor;

    /**
     * Resolves supported numeric operations, including signed literal boundaries.
     *
     * @param non-empty-list<Expression> $operands
     */
    public function arithmetic(string $operator, array $operands, Node $source): TypeDescriptor;

    /**
     * @throws SemanticException
     */
    public function predicate(Expression $expression): void;

    /**
     * @param list<Expression> $operands
     * @return list<Expression>
     */
    public function coalesce(array $operands, TypeDescriptor $type): array;

    /**
     * Resolves the output type of a projected literal.
     */
    public function project(Expression $expression): Expression;
}
