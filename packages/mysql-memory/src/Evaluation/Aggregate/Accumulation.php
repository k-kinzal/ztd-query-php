<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Aggregate;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;

/**
 * One aggregate of a query block: the function, its arguments, and the domain of its result.
 *
 * The arguments are evaluated over each input row of a group; Accumulator folds them.
 * GROUP_CONCAT has no function; its order and separator are given. JSON_OBJECTAGG folds as
 * JSON_ARRAYAGG does, into an object.
 *
 * @visibility MySqlMemory
 */
final class Accumulation
{
    /**
     * @param AggregateFunction|null $function The aggregate function, or null for GROUP_CONCAT
     * @param list<Evaluable> $arguments The arguments; none for COUNT(*)
     * @param bool $distinct Whether equal argument values are folded once
     * @param Domain $domain The domain of the result
     * @param list<array{Evaluable, bool}> $order The GROUP_CONCAT order keys, each with whether it is descending
     * @param string $separator The GROUP_CONCAT separator
     * @param int $limit The longest GROUP_CONCAT result in bytes (group_concat_max_len)
     * @param bool $object Whether the fold is JSON_OBJECTAGG, whose function is JSON_ARRAYAGG's and whose arguments are a name and a value
     */
    public function __construct(
        public readonly ?AggregateFunction $function,
        public readonly array $arguments,
        public readonly bool $distinct,
        public readonly Domain $domain,
        public readonly array $order = [],
        public readonly string $separator = ',',
        public readonly int $limit = 1024,
        public readonly bool $object = false,
    ) {
    }

    /**
     * Starts the fold of one group.
     */
    public function start(): Accumulator
    {
        return new Accumulator($this);
    }
}
