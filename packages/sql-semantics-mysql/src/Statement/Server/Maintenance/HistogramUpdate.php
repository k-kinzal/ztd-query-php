<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

/**
 * Whether a histogram is updated by the server's automatic statistics recalculation (MySQL 8.4 and later).
 *
 * Mirrors the auto_update flag of Histogram_param. MANUAL UPDATE is the
 * default. Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\HistogramUpdate::Automatic->value // => 'AUTO UPDATE'
 */
enum HistogramUpdate: string
{
    case Manual = 'MANUAL UPDATE';
    case Automatic = 'AUTO UPDATE';
}
