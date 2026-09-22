<?php

declare(strict_types=1);

namespace SqlSemantics;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Validation\InputViolation;
use Throwable;

/**
 * A diagnosed invalid request, distinct from an unclassified SQL implementation failure.
 * @visibility public
 * @example Inspecting an invalid input
 *     $error = new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::InsertWidth, new \SqlParser\Parser\Node('insert', 0, []));
 *     $error->violation->value // => 'insert-column-count'
 */
final class InvalidSql extends SemanticException
{
    /**
     * Describes a known operand invariant without manufacturing a valid statement.
     */
    public function __construct(public readonly InputViolation $violation, Node|Token $source, ?Throwable $previous = null)
    {
        parent::__construct($violation->value, $violation->message(), $source, $previous);
    }
}
