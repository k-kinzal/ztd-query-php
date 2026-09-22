<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

use Override;

/**
 * MySQL table storage options with classified values.
 *
 * @visibility public
 */
final class MySqlProperties implements Properties
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly bool $temporary = false,
        public readonly ?string $engine = null,
        public readonly ?string $characterSet = null,
        public readonly ?string $collation = null,
        public readonly ?string $comment = null,
        public readonly ?string $compression = null,
        public readonly ?string $encryption = null,
        public readonly ?string $connection = null,
        public readonly ?string $dataDirectory = null,
        public readonly ?string $indexDirectory = null,
        public readonly ?string $password = null,
        public readonly ?string $tablespace = null,
        public readonly ?string $engineAttribute = null,
        public readonly ?string $secondaryEngineAttribute = null,
        public readonly ?int $autoIncrement = null,
        public readonly ?int $averageRowLength = null,
        public readonly ?int $checksum = null,
        public readonly ?int $delayKeyWrite = null,
        public readonly ?int $keyBlockSize = null,
        public readonly ?int $maxRows = null,
        public readonly ?int $minRows = null,
        public readonly ?RowFormat $rowFormat = null,
        public readonly ?bool $packKeys = null,
        public readonly ?bool $statsAutoRecalc = null,
        public readonly ?bool $statsPersistent = null,
        public readonly ?int $statsSamplePages = null,
    ) {
    }
    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::MySql;
    }
}
