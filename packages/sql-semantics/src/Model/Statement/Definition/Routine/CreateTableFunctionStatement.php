<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Routine;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Declaration\ParameterDeclaration;
use SqlSemantics\Model\Definition\Routine\Declaration\ParameterInvariant;
use SqlSemantics\Model\Definition\Routine\Declaration\ResultColumn;
use SqlSemantics\Model\Definition\Routine\Declaration\RoutineImplementation;
use SqlSemantics\Model\Definition\Routine\Option\RoutineOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates or replaces a function that returns a set of rows with named columns (RETURNS TABLE).
 * @visibility public
 * @example Reading the result columns
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION pairs(n integer) RETURNS TABLE(k integer, v text) LANGUAGE sql ROWS 10 AS 'SELECT 1, 2'");
 *     $statement->columns[1]->name // => 'v'
 *     $statement->options[0]->rows->text // => '10'
 * @example Rejecting an empty column list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION pairs() RETURNS TABLE(k integer) LANGUAGE sql AS 'SELECT 1'");
 *     $statement->withColumns([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateTableFunctionStatement extends BoundStatement
{
    /**
     * @param bool $orReplace Whether an existing function with this signature is replaced (OR REPLACE)
     * @param list<ParameterDeclaration> $parameters
     * @param non-empty-list<ResultColumn> $columns
     * @param bool $window Whether the function is a window function (WINDOW)
     * @param list<RoutineOption|RoutineSecurity> $options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly bool $orReplace, public readonly QualifiedName $name, public readonly array $parameters, public readonly array $columns, public readonly RoutineImplementation $implementation, public readonly bool $window, public readonly array $options)
    {
        RoutineInvariant::definition($origin, $name, $parameters, $options, false);
        Collections::nonEmpty($columns);
        ParameterInvariant::table($parameters, $columns);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->orReplace, $this->name, $this->parameters, $this->columns, $this->implementation, $this->window, $this->options);
    }

    /**
     * Replaces whether an existing routine with this signature is replaced.
     */
    public function withOrReplace(bool $orReplace): self
    {
        return $this->changed(new self($this->origin, $orReplace, $this->name, $this->parameters, $this->columns, $this->implementation, $this->window, $this->options));
    }
    /**
     * Replaces the routine name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $name, $this->parameters, $this->columns, $this->implementation, $this->window, $this->options));
    }
    /**
     * Replaces the parameter declarations.
     * @param list<ParameterDeclaration> $parameters
     */
    public function withParameters(array $parameters): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $parameters, $this->columns, $this->implementation, $this->window, $this->options));
    }
    /**
     * Replaces the result columns.
     * @param non-empty-list<ResultColumn> $columns
     */
    public function withColumns(array $columns): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $this->parameters, $columns, $this->implementation, $this->window, $this->options));
    }
    /**
     * Replaces the language and body.
     */
    public function withImplementation(RoutineImplementation $implementation): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $this->parameters, $this->columns, $implementation, $this->window, $this->options));
    }
    /**
     * Replaces whether the function is a window function.
     */
    public function withWindow(bool $window): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $this->parameters, $this->columns, $this->implementation, $window, $this->options));
    }
    /**
     * Replaces the attributes.
     * @param list<RoutineOption|RoutineSecurity> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $this->parameters, $this->columns, $this->implementation, $this->window, $options));
    }
}
