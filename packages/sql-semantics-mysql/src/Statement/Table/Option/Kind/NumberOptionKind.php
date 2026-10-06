<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option\Kind;

/**
 * The table options whose value is a number, and those of them that also accept DEFAULT.
 *
 * Each case holds the keywords it is written with. TABLE_CHECKSUM is the
 * server's second spelling of CHECKSUM; the spelling is kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\NumberOptionKind::MaxRows->value // => 'MAX_ROWS'
 */
enum NumberOptionKind: string
{
    case MaxRows = 'MAX_ROWS';
    case MinRows = 'MIN_ROWS';
    case AverageRowLength = 'AVG_ROW_LENGTH';
    case AutoIncrement = 'AUTO_INCREMENT';
    case PackKeys = 'PACK_KEYS';
    case StatsAutoRecalc = 'STATS_AUTO_RECALC';
    case StatsPersistent = 'STATS_PERSISTENT';
    case StatsSamplePages = 'STATS_SAMPLE_PAGES';
    case Checksum = 'CHECKSUM';
    case TableChecksum = 'TABLE_CHECKSUM';
    case DelayKeyWrite = 'DELAY_KEY_WRITE';
    case KeyBlockSize = 'KEY_BLOCK_SIZE';

    /**
     * Tells whether the option accepts the keyword DEFAULT in place of a number.
     */
    public function defaultable(): bool
    {
        return match ($this) {
            self::PackKeys, self::StatsAutoRecalc, self::StatsPersistent, self::StatsSamplePages => true,
            self::MaxRows, self::MinRows, self::AverageRowLength, self::AutoIncrement, self::Checksum, self::TableChecksum, self::DelayKeyWrite, self::KeyBlockSize => false,
        };
    }
}
