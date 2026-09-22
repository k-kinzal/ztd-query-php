<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Copy;

use SqlSemantics\Schema\Table\MySqlProperties;

/**
 * Copies declaration storage options while resetting instance-specific table state.
 * @visibility SqlSemantics
 */
final class MySqlOptions
{
    /**
     * Uses the new TEMPORARY request, omitting counters and physical file directories.
     */
    public static function copy(MySqlProperties $properties, bool $temporary): MySqlProperties
    {
        return new MySqlProperties(
            temporary: $temporary,
            engine: $properties->engine,
            characterSet: $properties->characterSet,
            collation: $properties->collation,
            comment: $properties->comment,
            compression: $properties->compression,
            encryption: strtoupper($properties->encryption ?? '') === 'N' ? null : $properties->encryption,
            connection: $properties->connection,
            password: $properties->password,
            tablespace: $properties->tablespace,
            engineAttribute: $properties->engineAttribute,
            secondaryEngineAttribute: $properties->secondaryEngineAttribute,
            averageRowLength: $properties->averageRowLength,
            checksum: $properties->checksum,
            delayKeyWrite: $properties->delayKeyWrite,
            keyBlockSize: $properties->keyBlockSize,
            maxRows: $properties->maxRows,
            minRows: $properties->minRows,
            rowFormat: $properties->rowFormat,
            packKeys: $properties->packKeys,
            statsAutoRecalc: $properties->statsAutoRecalc,
            statsPersistent: $properties->statsPersistent,
            statsSamplePages: $properties->statsSamplePages,
        );
    }
}
