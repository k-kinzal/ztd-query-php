<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * A resource group: the CPUs and the thread priority of the threads assigned to it.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/resource-groups.html.
 *
 * @visibility MySqlMemory
 */
final class ResourceGroup
{
    /**
     * @param string $name The name, as the statement that created the group wrote it
     * @param bool $system Whether the group is of type SYSTEM, which only background threads join
     * @param list<int> $cpus The CPUs the threads of the group run on, in ascending order
     * @param int $priority The thread priority: -20 to 0 for a system group, 0 to 19 for a user group
     * @param bool $enabled Whether threads can be assigned to the group
     */
    public function __construct(public readonly string $name, public readonly bool $system, public array $cpus, public int $priority = 0, public bool $enabled = true)
    {
    }

    /**
     * Answers the CPUs as INFORMATION_SCHEMA.RESOURCE_GROUPS shows them: ranges of consecutive CPUs, separated by commas.
     *
     * @example Ranges and single CPUs
     *     (new \MySqlMemory\Registry\ResourceGroup('batch', false, [1, 2, 4, 5, 7]))->vcpus() // => '1-2,4-5,7'
     */
    public function vcpus(): string
    {
        $ranges = [];
        foreach ($this->cpus as $cpu) {
            $last = count($ranges) - 1;
            if ($last >= 0 && $ranges[$last][1] === $cpu - 1) {
                $ranges[$last][1] = $cpu;
            } else {
                $ranges[] = [$cpu, $cpu];
            }
        }

        return implode(',', array_map(static fn (array $range): string => $range[0] === $range[1] ? (string) $range[0] : $range[0] . '-' . $range[1], $ranges));
    }
}
