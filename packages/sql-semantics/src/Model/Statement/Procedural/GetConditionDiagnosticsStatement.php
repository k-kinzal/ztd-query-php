<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Procedural;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Condition\ConditionDiagnostic;
use SqlSemantics\Model\Configuration\Condition\DiagnosticsArea;
use SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference;
use SqlSemantics\Model\Scalar\Reference\VariableReference;
use SqlSemantics\Model\Scalar\Value\IntroducedLiteral;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\TemporalLiteral;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Copies condition information items of one numbered condition into user variables; the server evaluates the number.
 * @visibility public
 * @example Reading the condition number and items
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GET DIAGNOSTICS CONDITION 1 @state = RETURNED_SQLSTATE, @text = MESSAGE_TEXT');
 *     [$statement->condition->text, count($statement->items)] // => ['1', 2]
 */
final class GetConditionDiagnosticsStatement extends BoundStatement
{
    /**
     * @var non-empty-list<ConditionDiagnostic>
     */
    public readonly array $items;

    /**
     * @param Literal|IntroducedLiteral|TemporalLiteral|VariableReference|UnresolvedVariableReference $condition Condition number as a literal or variable
     * @param list<ConditionDiagnostic> $items Items in request order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly DiagnosticsArea $area, public readonly Literal|IntroducedLiteral|TemporalLiteral|VariableReference|UnresolvedVariableReference $condition, array $items)
    {
        if ($origin->dialect !== Dialect::MySql || $condition->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('GET DIAGNOSTICS requires MySQL.');
        }
        Collections::objects($items, ConditionDiagnostic::class);
        $this->items = Collections::nonEmpty($items);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::GetDiagnostics;
    }

    /**
     * Retains the area, condition and items while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->area, $this->condition, $this->items);
    }

    /**
     * Reads another diagnostics area.
     */
    public function withArea(DiagnosticsArea $area): self
    {
        return $this->changed(new self($this->origin, $area, $this->condition, $this->items));
    }

    /**
     * Reads another condition number.
     */
    public function withCondition(Literal|IntroducedLiteral|TemporalLiteral|VariableReference|UnresolvedVariableReference $condition): self
    {
        return $this->changed(new self($this->origin, $this->area, $condition, $this->items));
    }

    /**
     * Replaces the nonempty item list.
     * @param list<ConditionDiagnostic> $items
     */
    public function withItems(array $items): self
    {
        return $this->changed(new self($this->origin, $this->area, $this->condition, $items));
    }
}
