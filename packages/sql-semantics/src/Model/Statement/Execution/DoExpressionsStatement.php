<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Execution;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Reference\Wildcard;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\QueryComparison;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * A MySQL request to evaluate scalar expressions and discard their results.
 * @visibility public
 * @example Inspecting expressions without executing them
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('DO 1 + 2, 3');
 *     count($statement->expressions) // => 2
 * @example Rejecting an empty evaluation request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('DO 1');
 *     new \SqlSemantics\Model\Statement\Execution\DoExpressionsStatement($statement->origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DoExpressionsStatement extends BoundStatement
{
    /**
     * @param non-empty-list<Expression> $expressions Scalar expressions in SQL binding order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $expressions)
    {
        Collections::objects(Collections::nonEmpty($expressions), Expression::class);
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('DO expressions require MySQL.');
        }
        StatementOperands::expressions($expressions, $origin->dialect);
        foreach ($expressions as $expression) {
            if ($expression instanceof Wildcard || !in_array(QueryComparison::width($expression), [null, 1], true)) {
                throw new InvalidStructure('A discarded result requires a scalar expression.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Do;
    }

    /**
     * Retains the evaluation request when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->expressions);
    }

    /**
     * Replaces the expressions and derives their facts without performing their evaluation.
     * @param non-empty-list<Expression> $expressions New scalar operands in binding order
     */
    public function withExpressions(array $expressions): self
    {
        return $this->changed(new self($this->origin, $expressions));
    }
}
