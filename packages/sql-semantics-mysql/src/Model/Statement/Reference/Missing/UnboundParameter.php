<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing;

use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A statement parameter whose value, and therefore type, is supplied only at execution.
 *
 * @visibility SqlSemantics
 * @example Naming an unbound parameter
 *     (new \SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\UnboundParameter('?1'))->describe() // => 'the value bound to parameter ?1'
 */
final class UnboundParameter implements MissingInput
{
    use Snapshot;

    /**
     * @param string $marker The parameter as the statement identifies it
     */
    public function __construct(public readonly string $marker)
    {
    }

    /**
     * Describes the missing value.
     */
    public function describe(): string
    {
        return 'the value bound to parameter ' . $this->marker;
    }
}
