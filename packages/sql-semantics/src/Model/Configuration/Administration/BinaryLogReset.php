<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Administration;

use Override;
use SqlSemantics\Model\Configuration\Replication\ReplicationNumber;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * RESET BINARY LOGS AND GTIDS (RESET MASTER): deletes every binary log file and clears the executed GTID set, optionally numbering the next log file.
 * @visibility public
 * @example Starting without an explicit index
 *     (new \SqlSemantics\Model\Configuration\Administration\BinaryLogReset())->firstIndex // => null
 */
final class BinaryLogReset implements ResetTarget
{
    /**
     * The first index is an integer from 1 to 2000000000.
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?Literal $firstIndex = null)
    {
        if ($firstIndex === null) {
            return;
        }
        ReplicationNumber::check($firstIndex, 'A binary log index', true);
        $index = ReplicationNumber::magnitude($firstIndex);
        if ($index < 1 || $index > 2000000000) {
            throw new InvalidStructure('A binary log index is an integer from 1 to 2000000000.');
        }
    }

    /**
     * TO exists from MySQL 8.0.
     */
    #[Override]
    public function availableIn(int $release): bool
    {
        return $this->firstIndex === null || $release >= 80000;
    }
}
