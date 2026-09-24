<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\ResourceGroup;

use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates resource group requests: MySQL 8.0 or later, a name the server accepts, CPU ranges and a thread priority.
 * @visibility SqlSemantics
 */
final class ResourceGroupOptions
{
    /**
     * @param list<CpuRange> $cpus
     * @param ThreadCategory|null $type Group type fixing the priority range; null allows every type's range
     * @throws InvalidStructure
     */
    public static function validate(Origin $origin, string $name, array $cpus = [], ?int $priority = null, ?ThreadCategory $type = null): void
    {
        ReplicationRelease::require($origin, 'Resource groups', 80000);
        if ($name === '' || mb_strlen($name) > 64) {
            throw new InvalidStructure('A resource group name has 1 to 64 characters.');
        }
        Collections::objects($cpus, CpuRange::class);
        $highest = $type?->highestPriority() ?? ThreadCategory::System->highestPriority();
        $lowest = $type?->lowestPriority() ?? ThreadCategory::User->lowestPriority();
        if ($priority !== null && ($priority < $highest || $priority > $lowest)) {
            throw new InvalidStructure('The thread priority is outside the range the resource group type allows.');
        }
    }
}
