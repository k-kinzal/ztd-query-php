<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Transaction\Xa;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Transaction\Xa\RecoveryColumn;
use SqlSemantics\Model\Transaction\Xa\RecoveryEncoding;
use SqlSemantics\Model\Transaction\Xa\RecoveryField;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests the prepared XA branches, with no input transaction identifier.
 * @visibility public
 * @example Inspecting result columns
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('XA RECOVER');
 *     array_column($statement->resultColumns(), 'name') // => ['formatID', 'gtrid_length', 'bqual_length', 'data']
 */
final class XaRecoverStatement extends BoundStatement implements ResultStatement
{
    /**
     * Records the requested identifier encoding without reading server transaction state.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly RecoveryEncoding $encoding = RecoveryEncoding::Bytes)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('XA recovery requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::XaRecover;
    }

    /**
     * Retains the recovery request when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->encoding);
    }

    /**
     * Replaces the recovery encoding in a new validated snapshot.
     */
    public function withEncoding(RecoveryEncoding $encoding): self
    {
        return $this->changed(new self($this->origin, $encoding));
    }

    /**
     * @return list<OutputColumn> Fixed result roles, names, and types; no runtime rows
     */
    #[Override]
    public function resultColumns(): array
    {
        $columns = [];
        foreach (RecoveryField::cases() as $ordinal => $field) {
            $columns[] = new OutputColumn($ordinal, $field->value, new RecoveryColumn($this->source, $this->scopeId, $field, $this->encoding));
        }
        return $columns;
    }
}
