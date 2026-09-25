<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlParser\Parser\Node;

/**
 * Common diagnostic provenance of an immutable SQL operation.
 * Each concrete operation owns only its own operands and clauses.
 *
 * @visibility public
 * @example Binding an operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     (new \SqlSemantics\Binder($schema))->bind('SELECT 1')->kind->value // => 'SELECT'
 */
abstract class BoundStatement
{
    /**
     * Identity of the binding scope that owns this operation.
     */
    public readonly string $scopeId;
    /**
     * Original syntax retained only for diagnostic locations and provenance.
     */
    public readonly Node $source;
    /**
     * @var list<Diagnostic>
     */
    public readonly array $diagnostics;
    /**
     * Operation identity derived from the concrete statement type.
     */
    public readonly Statement\StatementKind $kind;

    /**

     * @visibility SqlSemantics

     */
    public function __construct(public readonly Statement\Origin $origin)
    {
        $this->scopeId = $origin->scopeId;
        $this->source = $origin->source;
        $this->diagnostics = $origin->diagnostics;
        $this->kind = $this->operation();
    }

    /**

     * Returns the identity fixed by this concrete statement form.

     */
    abstract protected function operation(): Statement\StatementKind;

    /**

     * @visibility SqlSemantics

     */
    abstract public function withOrigin(Statement\Origin $origin): static;

    /**
     * @param list<Diagnostic> $diagnostics
     * @visibility SqlSemantics
     */
    public function withDiagnostics(array $diagnostics): static
    {
        return $this->withOrigin(new Statement\Origin($this->scopeId, $this->source, $this->origin->dialect, $diagnostics, $this->origin->context, $this->origin->verbatim));
    }

    /**
     * Writes the SQL text of this statement.
     *
     * A statement read by Binder writes back exactly the SQL text it was bound from. A statement
     * produced by a transformation or constructed from operands has no original text to keep, so
     * it is written from its semantic operands in the standard compact layout.
     */
    public function toString(): string
    {
        return $this->origin->verbatim ? $this->source->toString() : (new \SqlSemantics\SimpleSerializer())->serialize($this);
    }

    /**
     * Marks the operation as exactly the one read from its source text, so it writes that text back.
     *
     * @visibility SqlSemantics
     */
    public function withVerbatimSource(): static
    {
        return $this->withOrigin(new Statement\Origin($this->scopeId, $this->source, $this->origin->dialect, $this->diagnostics, $this->origin->context, true));
    }

    /**
     * Attaches the semantic validator used for all changes to this snapshot.
     *
     * @visibility SqlSemantics
     */
    public function withContext(Transformation\Context $context): static
    {
        return $this->withOrigin(new Statement\Origin($this->scopeId, $this->source, $this->origin->dialect, $this->diagnostics, $context, $this->origin->verbatim));
    }

    /**
     * Replaces one expression by a structured value and validates the complete result.
     *
     * @throws Validation\InvalidStructure
     */
    public function replaceExpression(Expression $target, Expression $replacement): static
    {
        if (!in_array($target, Traversal\Expressions::all($this), true)) {
            throw new Validation\InvalidStructure('The expression does not belong to this statement.');
        }
        if ($replacement->type->dialect !== $this->context()->schema()->dialect) {
            throw new Validation\InvalidStructure('A replacement expression must use the statement dialect.');
        }
        return $this->changed(Transformation\ExpressionEdit::replace($this, $target, $replacement));
    }

    /**
     * @visibility SqlSemantics
     * @throws Validation\InvalidStructure
     */
    protected function context(): Transformation\Context
    {
        if ($this->origin->context === null) {
            throw new Validation\InvalidStructure('A statement transformation requires its schema context.');
        }
        return $this->origin->context;
    }

    /**
     * Validates the complete structure and refreshes names, types, and dependencies.
     * @throws Validation\InvalidStructure
     */
    protected function changed(self $candidate): static
    {
        if (!$candidate instanceof static) {
            throw new Validation\InvalidStructure('A transformation must preserve the concrete statement form.');
        }
        return $this->context()->rebind($this, \SqlSemantics\Serialization\Statements::write($candidate));
    }
}
