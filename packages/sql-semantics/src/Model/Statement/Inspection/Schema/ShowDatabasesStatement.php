<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Schema;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Schema\DatabaseField;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Lists the databases visible to the current account.
 * @visibility public
 * @example Inspecting a filtered listing
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SHOW SCHEMAS LIKE 'app%'");
 *     $statement->filter instanceof \SqlSemantics\Model\Query\Inspection\Filter\PatternFilter // => true
 */
final class ShowDatabasesStatement extends InspectionStatement
{
    /**
     * @param PatternFilter|ConditionFilter|null $filter Restriction on the listed entries; null lists every entry
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly PatternFilter|ConditionFilter|null $filter = null)
    {
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->filter);
    }

    /**
     * Replaces the name restriction; null lists every database.
     */
    public function withFilter(PatternFilter|ConditionFilter|null $filter): self
    {
        return $this->changed(new self($this->origin, $filter));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(DatabaseField::cases());
    }
}
