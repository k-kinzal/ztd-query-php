<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

use SqlSemantics\Platform\MySql\Statement\Account\Privilege\WithOption;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * One resource limit of the WITH clause of CREATE USER, ALTER USER or MySQL 5.x GRANT.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-resource-limits.
 *
 * @visibility public
 * @example Reading a limit
 *     $limit = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE USER u WITH MAX_QUERIES_PER_HOUR 10')->statement->resources[0];
 *     [$limit->kind, $limit->number->text] // => [\SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceKind::QueriesPerHour, '10']
 */
final class ResourceLimit implements WithOption
{
    use Snapshot;

    /**
     * @param ResourceKind $kind The limited resource
     * @param Numeral $number The limit as written
     */
    public function __construct(public readonly ResourceKind $kind, public readonly Numeral $number)
    {
    }

    /**
     * Writes the limit.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->number);
    }
}
