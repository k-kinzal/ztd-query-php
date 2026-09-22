<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Prepared;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Invokes a PostgreSQL prepared query with ordered argument expressions.
 * @example Reading the operation's structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('EXECUTE s(1, 2)', strict: false);
 *     $statement->toString() // => 'EXECUTE "s"(1, 2)'
 *
 * @visibility public
 */
final class ExecuteQueryStatement extends BoundStatement
{
    /**
     * @param list<Expression> $arguments
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly array $arguments = [])
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This prepared-statement form requires PostgreSql.');
        }
        Collections::objects($arguments, Expression::class);
        foreach ($arguments as $argument) {
            if ($argument->type->dialect !== $origin->dialect) {
                throw new InvalidStructure('Prepared-query arguments must use the enclosing dialect.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Execute;
    }

    /**
     * Retains the prepared-statement operands when replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->arguments);
    }
}
