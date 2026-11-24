<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The native normalized pending page-size request.
 *
 * @visibility public
 * @example Requesting a page geometry of 8192 bytes
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\RequestedPageSize(8192))->bytes // => 8192
 */
final class RequestedPageSize
{
    use Snapshot;

    /**
     * Keeps the exact signed input, including requests the pager cannot apply.
     * @throws InvalidConstruction When the signed input exceeds the native range
     */
    public function __construct(public readonly int $bytes)
    {
        Check::input($bytes >= -2147483648 && $bytes <= 2147483647, 'A pending page-size request needs a signed 32-bit input.');
    }
}
