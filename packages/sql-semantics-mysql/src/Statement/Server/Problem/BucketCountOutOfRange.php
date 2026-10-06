<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A histogram bucket count outside 1 to 1024.
 *
 * The server rejects the statement with ER_DATA_OUT_OF_RANGE while it
 * parses it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Problem\BucketCountOutOfRange(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('0')))->message() // => 'Number of buckets 0 is out of range in ANALYZE TABLE: it is 1 to 1024.'
 */
final class BucketCountOutOfRange implements Diagnostic
{
    use Snapshot;

    /**
     * @param Numeral $buckets The bucket count as written
     */
    public function __construct(public readonly Numeral $buckets)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Number of buckets ' . $this->buckets->text . ' is out of range in ANALYZE TABLE: it is 1 to 1024.';
    }
}
