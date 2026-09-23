<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\ProcessColumn;
use SqlSemantics\Model\Query\Inspection\ProcessField;
use SqlSemantics\Model\Query\Inspection\ProcessInfoColumn;
use SqlSemantics\Model\Query\Inspection\ProcessQueryText;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests visible connection activity, with an explicit SQL-text truncation policy.
 * @visibility public
 * @example Inspecting the query-text request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW FULL PROCESSLIST');
 *     $statement->queryText === \SqlSemantics\Model\Query\Inspection\ProcessQueryText::Complete // => true
 */
final class ShowProcessesStatement extends BoundStatement implements ResultStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ProcessQueryText $queryText = ProcessQueryText::Preview)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('SHOW PROCESSLIST requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Show;
    }

    /**
     * Retains the inspection request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->queryText);
    }

    /**
     * Replaces the requested query-text detail without reading connection state.
     */
    public function withQueryText(ProcessQueryText $queryText): self
    {
        return $this->changed(new self($this->origin, $queryText));
    }

    /**
     * @return list<OutputColumn> Fixed process roles and query-text precision
     */
    #[Override]
    public function resultColumns(): array
    {
        $columns = [];
        foreach (ProcessField::cases() as $ordinal => $field) {
            $columns[] = new OutputColumn($ordinal, $field->value, new ProcessColumn($this->source, $this->scopeId, $field));
        }
        $columns[] = new OutputColumn(count($columns), 'Info', new ProcessInfoColumn($this->source, $this->scopeId, $this->queryText));
        return $columns;
    }
}
