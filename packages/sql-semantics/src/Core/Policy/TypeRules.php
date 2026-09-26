<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\Operator;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Builtin;
use SqlSemantics\Core\Type\TypeDeclaration;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * Supplies scalar type interpretation and coercion.
 *
 * @visibility SqlSemantics
 */
interface TypeRules
{
    /**
     * Reads a declared type into typed facts, including table-dependent storage rules and implied column facts.
     *
     * @param ValueReader $values Lowers declaration parts that the type keeps as typed values
     * @param Node|null $table Enclosing table declaration, for options that change how a type is stored
     * @throws SemanticException When the declaration is outside the modeled surface or invalid
     */
    public function read(Node $node, ValueReader $values, ?Node $table = null): TypeDeclaration;

    /**
     * Reports whether this dialect has the built-in type.
     */
    public function supports(Builtin $type): bool;

    /**
     * Types a literal terminal without converting its contents, or returns null for a non-literal.
     *
     * @throws SemanticException When the literal form is outside the modeled surface
     */
    public function literal(Token $token): ?TypeDescriptor;

    /**
     * Chooses an integer width from its decimal spelling.
     */
    public function integer(string $text): Builtin;

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
    public function arithmetic(Operator $operator, array $operands, Node $source): TypeDescriptor;

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
