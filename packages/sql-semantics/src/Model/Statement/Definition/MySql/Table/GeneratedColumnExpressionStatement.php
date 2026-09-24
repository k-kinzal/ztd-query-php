<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Table;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A PARSE_GCOL_EXPR request, the MySQL 5.7 parser entry the server uses to read a stored generated column expression.
 * @visibility public
 * @example Reading a stored generation expression
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('PARSE_GCOL_EXPR (1 + 2)');
 *     $statement->expression instanceof \SqlSemantics\Model\Scalar\Operator\BinaryExpression // => true
 *     $statement->toString() // => 'PARSE_GCOL_EXPR((1 + 2))'
 */
final class GeneratedColumnExpressionStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly Expression $expression)
    {
        TableInvariant::dialect($origin);
        TableInvariant::only($origin, ['mysql-5.7.44'], 'PARSE_GCOL_EXPR');
        AlterationInvariant::expression($expression);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::ParseGeneratedColumnExpression;
    }

    /**
     * Retains the expression while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->expression);
    }

    /**
     * Replaces the generation expression.
     */
    public function withExpression(Expression $expression): self
    {
        return $this->changed(new self($this->origin, $expression));
    }
}
