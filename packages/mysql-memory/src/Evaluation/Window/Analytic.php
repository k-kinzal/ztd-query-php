<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Window;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

/**
 * One call of a window function, or of an aggregate used as one, ready to be computed for each row of a partition.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-descriptions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Analytic
{
    /**
     * @param WindowFunctionKind|null $kind The window function, or null for an aggregate
     * @param Accumulation|null $accumulation The aggregate, when the call is one
     * @param list<Evaluable> $arguments The value LEAD, LAG, FIRST_VALUE, LAST_VALUE and NTH_VALUE read, then the default of LEAD and LAG
     * @param int $count The number of buckets of NTILE, the offset of LEAD and LAG, or the row of the frame NTH_VALUE reads
     * @param Domain $domain The domain of the result
     */
    public function __construct(
        public readonly ?WindowFunctionKind $kind,
        public readonly ?Accumulation $accumulation,
        public readonly array $arguments,
        public readonly int $count,
        public readonly Domain $domain,
    ) {
    }
}
