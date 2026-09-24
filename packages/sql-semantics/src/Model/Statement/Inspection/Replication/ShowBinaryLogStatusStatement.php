<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Replication;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Replication\BinaryLogStatusField;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Reports the current binary log file and position; SHOW MASTER STATUS is the spelling of releases before 8.4.
 * @visibility public
 * @example Inspecting the result fields
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW BINARY LOG STATUS');
 *     array_column($statement->resultColumns(), 'name') // => ['File', 'Position', 'Binlog_Do_DB', 'Binlog_Ignore_DB', 'Executed_Gtid_Set']
 */
final class ShowBinaryLogStatusStatement extends InspectionStatement
{
    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin);
    }

    /**
     * Whether the grammar release spells the request SHOW BINARY LOG STATUS; releases before 8.2 know only SHOW MASTER STATUS, and 8.2 and 8.3 accept both.
     */
    public function binaryLogSpelling(): bool
    {
        return !in_array($this->origin->context?->schema()->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0'], true);
    }

    /**
     * @return list<OutputColumn> The file, position, filter, and executed GTID fields
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(BinaryLogStatusField::cases());
    }
}
