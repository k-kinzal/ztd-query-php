<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The normalized positive page limit of a stock SQLite pager.
 *
 * @visibility public
 * @example Selecting the native upper boundary
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PageLimit('4294967294'))->decimal // => '4294967294'
 */
final class PageLimit
{
    use Snapshot;

    /**
     * The largest supported native pager limit.
     */
    public const MAXIMUM = '4294967294';

    /**
     * Checks a canonical positive native limit independently of PHP integer width.
     * @throws InvalidConstruction When the decimal spelling or range is invalid
     */
    public function __construct(public readonly string $decimal)
    {
        Check::input(
            preg_match('/\A[1-9][0-9]{0,9}\z/', $decimal) === 1
                && (strlen($decimal) < 10 || strcmp($decimal, self::MAXIMUM) <= 0),
            'A page limit requires a canonical decimal from 1 to 4294967294.',
        );
    }
}
