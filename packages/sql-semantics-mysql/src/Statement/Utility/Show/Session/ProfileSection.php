<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Session;

/**
 * A group of columns SHOW PROFILE adds to the stage and its duration.
 *
 * Each case holds its keywords. MEMORY is accepted and adds no column, as
 * the server does not implement it; ALL adds every group.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-profile.html.
 *
 * @visibility public
 * @example Reading the keywords of a section
 *     \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ProfileSection::ContextSwitches->value // => 'CONTEXT SWITCHES'
 */
enum ProfileSection: string
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
     * Answers the positions of the columns the section adds in the layout with every section.
     *
     * @return list<int>
     * @example Reading the columns of the CPU section
     *     \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ProfileSection::Cpu->positions() // => [2, 3]
     */
    public function positions(): array
    {
        return match ($this) {
            self::All => range(2, 15),
            self::Cpu => [2, 3],
            self::ContextSwitches => [4, 5],
            self::BlockIo => [6, 7],
            self::Ipc => [8, 9],
            self::PageFaults => [10, 11],
            self::Swaps => [12],
            self::Source => [13, 14, 15],
            self::Memory => [],
        };
    }
}
