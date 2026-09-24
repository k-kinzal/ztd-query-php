<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Session;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Session\VariableField;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Query\Inspection\Session\VariableScope;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Lists the server's status counters with their values in one scope.
 * @visibility public
 * @example Inspecting the scope and restriction
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SHOW GLOBAL STATUS LIKE 'Uptime'");
 *     [$statement->scope->value, $statement->filter instanceof \SqlSemantics\Model\Query\Inspection\Filter\PatternFilter] // => ['GLOBAL', true]
 */
final class ShowStatusStatement extends InspectionStatement
{
    /**
     * @param VariableScope $scope Values reported; LOCAL and an omitted scope select the session
     * @param PatternFilter|ConditionFilter|null $filter Restriction on the listed names; null lists every entry
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly VariableScope $scope = VariableScope::Session, public readonly PatternFilter|ConditionFilter|null $filter = null)
    {
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->scope, $this->filter);
    }

    /**
     * Reports the values of another scope.
     */
    public function withScope(VariableScope $scope): self
    {
        return $this->changed(new self($this->origin, $scope, $this->filter));
    }

    /**
     * Replaces the name restriction; null lists every entry.
     */
    public function withFilter(PatternFilter|ConditionFilter|null $filter): self
    {
        return $this->changed(new self($this->origin, $this->scope, $filter));
    }

    /**
     * @return list<OutputColumn> The name and value fields
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(VariableField::cases());
    }
}
