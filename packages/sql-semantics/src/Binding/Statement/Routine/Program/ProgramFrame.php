<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ErrorCode;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalVariable;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Trigger\Timing;

/**
 * What one point of a stored program body can see: its program kind, variables, conditions, cursors and labels.
 * @visibility SqlSemantics
 */
final class ProgramFrame
{
    /**
     * @param array<string, ErrorCode|SqlState> $conditions Visible conditions by case-folded name
     * @param array<string, true> $cursors Visible cursors by case-folded name
     * @param array<string, bool> $labels Enclosing labels of the current handler scope; true for loops
     */
    public function __construct(
        public readonly ProgramKind $kind,
        public readonly QueryContext $context,
        public readonly ?Timing $timing = null,
        public readonly array $conditions = [],
        public readonly array $cursors = [],
        public readonly array $labels = [],
    ) {
    }

    /**
     * Starts a body frame whose expressions resolve against the given namespace.
     */
    public static function start(ProgramKind $kind, QueryContext $context, ProgramNamespace $names, ?Timing $timing = null): self
    {
        return new self($kind, self::context($context, $names), $timing);
    }

    /**
     * Derives a query context whose tables resolve program names; identities stay shared.
     */
    public static function context(QueryContext $context, ProgramNamespace $names): QueryContext
    {
        $tables = $context->tables;
        return new QueryContext(new TableResolver($tables->schema, $tables->identifiers, $tables->defaultSchema, $tables->diagnostics, $names), $context->ids, $context->ctes, $context->withClause, $context->parameterTypes);
    }

    /**
     * Returns the namespace of program names visible here.
     */
    public function names(): ProgramNamespace
    {
        return $this->context->tables->program ?? new ProgramNamespace();
    }

    /**
     * Returns an expression scope without relations, in which program names resolve.
     */
    public function scope(): Scope
    {
        return new Scope($this->context->tables->identifiers, queries: $this->context);
    }

    /**
     * Binds an expression of the body, in which program names resolve before columns.
     */
    public function expression(Node $node): Expression
    {
        return (new ExpressionBinder())->bind($node, $this->scope());
    }

    /**
     * Adds variables that shadow earlier ones of the same name.
     * @param list<LocalVariable> $variables
     */
    public function withVariables(array $variables): self
    {
        return new self($this->kind, self::context($this->context, $this->names()->declare($variables)), $this->timing, $this->conditions, $this->cursors, $this->labels);
    }

    /**
     * Adds a named condition.
     */
    public function withCondition(string $name, ErrorCode|SqlState $value): self
    {
        return new self($this->kind, $this->context, $this->timing, [...$this->conditions, strtolower($name) => $value], $this->cursors, $this->labels);
    }

    /**
     * Adds a cursor name.
     */
    public function withCursor(string $name): self
    {
        return new self($this->kind, $this->context, $this->timing, $this->conditions, [...$this->cursors, strtolower($name) => true], $this->labels);
    }

    /**
     * Adds an enclosing block or loop label.
     */
    public function withLabel(string $label, bool $loop): self
    {
        return new self($this->kind, $this->context, $this->timing, $this->conditions, $this->cursors, [...$this->labels, strtolower($label) => $loop]);
    }

    /**
     * Enters a handler body, where labels outside the handler are not visible.
     */
    public function inHandler(): self
    {
        return new self($this->kind, $this->context, $this->timing, $this->conditions, $this->cursors);
    }
}
