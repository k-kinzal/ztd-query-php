<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

/**
 * Aggregate invocations with argument forms other than AggregateFunction.
 *
 * @visibility public
 * @example Reading the spelling of a retained JSON aggregate
 *     \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateKind::JsonObject->value // => 'JSON_OBJECTAGG'
 */
enum DeferredAggregateKind: string
{
    case GroupConcat = 'GROUP_CONCAT';
    case JsonObject = 'JSON_OBJECTAGG';
}
