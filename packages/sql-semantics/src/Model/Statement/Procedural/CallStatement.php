<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Procedural;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Invokes a stored procedure with ordered argument expressions; the procedure body is not run by binding.
 * @visibility public
 * @example Reading the procedure and its arguments
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CALL app.refresh(1, 'since')");
 *     [$statement->procedure->parts, count($statement->arguments)] // => [['app', 'refresh'], 2]
 */
final class CallStatement extends BoundStatement
{
    /**
     * @param QualifiedName $procedure Procedure name with an optional database part
     * @param list<Expression> $arguments Argument expressions in parameter order; empty for a procedure without parameters
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $procedure, public readonly array $arguments = [])
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('This CALL form requires MySQL.');
        }
        ProcedureName::validate($procedure);
        Collections::objects($arguments, Expression::class);
        foreach ($arguments as $argument) {
            if ($argument->type->dialect !== Dialect::MySql) {
                throw new InvalidStructure('Procedure arguments must use the statement dialect.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Call;
    }

    /**
     * Retains the procedure and its arguments while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->procedure, $this->arguments);
    }

    /**
     * Calls another procedure with the same arguments.
     */
    public function withProcedure(QualifiedName $procedure): self
    {
        return $this->changed(new self($this->origin, $procedure, $this->arguments));
    }

    /**
     * Replaces the ordered argument expressions.
     * @param list<Expression> $arguments
     */
    public function withArguments(array $arguments): self
    {
        return $this->changed(new self($this->origin, $this->procedure, $arguments));
    }
}
