<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Routine;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Declaration\ParameterDeclaration;
use SqlSemantics\Model\Definition\Routine\Declaration\RoutineImplementation;
use SqlSemantics\Model\Definition\Routine\Declaration\RoutineResult;
use SqlSemantics\Model\Definition\Routine\Option\OptionInvariant;
use SqlSemantics\Model\Definition\Routine\Option\RoutineOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates or replaces a function with a declared result type, a single value or a set (RETURNS [SETOF] type).
 * @visibility public
 * @example Reading the function definition
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE OR REPLACE FUNCTION app.add(a integer, b integer DEFAULT 1) RETURNS integer LANGUAGE sql IMMUTABLE STRICT AS 'SELECT a + b'");
 *     $statement->name->parts // => ['app', 'add']
 *     $statement->result->type->name // => 'integer'
 *     $statement->implementation->language // => 'sql'
 *     $statement->options // => [\SqlSemantics\Model\Definition\Routine\Option\Volatility::Immutable, \SqlSemantics\Model\Definition\Routine\Option\NullInputBehavior::Strict]
 * @example Rejecting a row estimate for a single-valued result
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE FUNCTION f() RETURNS integer RETURN 1');
 *     $statement->withOptions([new \SqlSemantics\Model\Definition\Routine\Option\ResultRows(\SqlSemantics\Model\Expression::literal(10, \SqlSemantics\Dialect::PostgreSql))]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateFunctionStatement extends BoundStatement
{
    /**
     * @param bool $orReplace Whether an existing function with this signature is replaced (OR REPLACE)
     * @param list<ParameterDeclaration> $parameters
     * @param RoutineResult $result Declared result (RETURNS)
     * @param bool $window Whether the function is a window function (WINDOW)
     * @param list<RoutineOption|RoutineSecurity> $options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly bool $orReplace, public readonly QualifiedName $name, public readonly array $parameters, public readonly RoutineResult $result, public readonly RoutineImplementation $implementation, public readonly bool $window, public readonly array $options)
    {
        RoutineInvariant::definition($origin, $name, $parameters, $options, false);
        OptionInvariant::rows($options, $result->setOf);
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
        return new self($origin, $this->orReplace, $this->name, $this->parameters, $this->result, $this->implementation, $this->window, $this->options);
    }

    /**
     * Replaces whether an existing routine with this signature is replaced.
     */
    public function withOrReplace(bool $orReplace): self
    {
        return $this->changed(new self($this->origin, $orReplace, $this->name, $this->parameters, $this->result, $this->implementation, $this->window, $this->options));
    }
    /**
     * Replaces the routine name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $name, $this->parameters, $this->result, $this->implementation, $this->window, $this->options));
    }
    /**
     * Replaces the parameter declarations.
     * @param list<ParameterDeclaration> $parameters
     */
    public function withParameters(array $parameters): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $parameters, $this->result, $this->implementation, $this->window, $this->options));
    }
    /**
     * Replaces the declared result.
     */
    public function withResult(RoutineResult $result): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $this->parameters, $result, $this->implementation, $this->window, $this->options));
    }
    /**
     * Replaces the language and body.
     */
    public function withImplementation(RoutineImplementation $implementation): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $this->parameters, $this->result, $implementation, $this->window, $this->options));
    }
    /**
     * Replaces whether the function is a window function.
     */
    public function withWindow(bool $window): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $this->parameters, $this->result, $this->implementation, $window, $this->options));
    }
    /**
     * Replaces the attributes.
     * @param list<RoutineOption|RoutineSecurity> $options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->orReplace, $this->name, $this->parameters, $this->result, $this->implementation, $this->window, $options));
    }
}
