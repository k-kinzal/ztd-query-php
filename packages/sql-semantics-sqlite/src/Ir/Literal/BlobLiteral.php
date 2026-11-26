<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Literal;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The exact bytes of a SQLite BLOB constant, as hexadecimal pairs.
 *
 * @visibility public
 * @example Holding bytes zero and 255
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral('00FF'))->hex // => '00FF'
 */
final class BlobLiteral
{
    use Snapshot;

    /**
     * Checks that each byte has one complete hexadecimal pair.
     */
    public function __construct(public readonly string $hex)
    {
        Check::input(strlen($hex) % 2 === 0 && preg_match('/\A[0-9A-F]*\z/', $hex) === 1, 'BLOB literal requires pairs of upper-case hexadecimal digits.');
    }
}
