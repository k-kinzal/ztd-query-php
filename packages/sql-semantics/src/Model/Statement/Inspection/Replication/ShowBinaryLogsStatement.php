<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Replication;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Replication\BinaryLogField;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Lists the binary log files on the server; SHOW MASTER LOGS is a synonym.
 * @visibility public
 * @example Inspecting the result fields
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW BINARY LOGS');
 *     array_column($statement->resultColumns(), 'name') // => ['Log_name', 'File_size', 'Encrypted']
 */
final class ShowBinaryLogsStatement extends InspectionStatement
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
     * @return list<OutputColumn> The file name and size, and the encryption flag on releases that report it
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns($this->legacyRelease() ? [BinaryLogField::Name, BinaryLogField::Size] : BinaryLogField::cases());
    }
}
