<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Profile;

/**
 * Resource categories a SHOW PROFILE request can include.
 * @visibility public
 * @example Inspecting a requested profile category
 *     \SqlSemantics\Model\Query\Inspection\Profile\ProfileCategory::BlockIo->value // => 'BLOCK IO'
 */
enum ProfileCategory: string
{
    case All = 'ALL';
    case BlockIo = 'BLOCK IO';
    case ContextSwitches = 'CONTEXT SWITCHES';
    case Cpu = 'CPU';
    case Ipc = 'IPC';
    case Memory = 'MEMORY';
    case PageFaults = 'PAGE FAULTS';
    case Source = 'SOURCE';
    case Swaps = 'SWAPS';

    /**
     * @param list<self> $requested Categories in request order, possibly repeated or including ALL
     * @return list<self> Distinct measured categories in server result order, expanding ALL
     */
    public static function measured(array $requested): array
    {
        $order = [self::Cpu, self::ContextSwitches, self::BlockIo, self::Ipc, self::PageFaults, self::Swaps, self::Source];
        if (in_array(self::All, $requested, true)) {
            return $order;
        }
        return array_values(array_filter($order, static fn (self $category): bool => in_array($category, $requested, true)));
    }
}
