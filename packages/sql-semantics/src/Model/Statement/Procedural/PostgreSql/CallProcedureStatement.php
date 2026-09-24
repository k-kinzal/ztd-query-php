<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Procedural\PostgreSql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Invokes a PostgreSQL procedure with positional and named arguments; binding neither resolves nor runs the procedure.
 * @visibility public
 * @example Reading the procedure and a variadic argument
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CALL app.merge(VARIADIC ARRAY[1, 2])');
 *     [$statement->procedure->parts, $statement->arguments[0]->variadic] // => [['app', 'merge'], true]
 * @example Rejecting a positional argument after a named one
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CALL p(a => 1, 2)'); // throws \SqlSemantics\InvalidSql
 */
final class CallProcedureStatement extends BoundStatement
{
    /**
     * @param QualifiedName $procedure Procedure name with optional catalog and schema parts
     * @param list<ProcedureArgument> $arguments Arguments in written order; named arguments follow positional ones
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $procedure, public readonly array $arguments = [])
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This CALL form requires PostgreSQL.');
        }
        if (count($procedure->parts) > 3) {
            throw new InvalidStructure('A procedure name has at most catalog, schema and procedure components.');
        }
        Collections::objects($arguments, ProcedureArgument::class);
        ProcedureArguments::validate($arguments);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Call;
    }

    /**
     * Retains the call while replacing diagnostic provenance.
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
     * Replaces the arguments.
     * @param list<ProcedureArgument> $arguments
     */
    public function withArguments(array $arguments): self
    {
        return $this->changed(new self($this->origin, $this->procedure, $arguments));
    }
}
