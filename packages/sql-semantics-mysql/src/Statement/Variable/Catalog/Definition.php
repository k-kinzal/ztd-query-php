<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable\Catalog;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Snapshot;

/**
 * One system variable of a release: its name, reach, values, writability and the type a read of it has.
 *
 * @visibility public
 * @example Reading the type of autocommit in 8.4
 *     \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables::of(\SqlSemantics\Contract\GrammarRelease::MySql847)->find('AUTOCOMMIT')?->domain->name() // => 'BIGINT'
 */
final class Definition
{
    use Snapshot;

    /**
     * @param string $name The name in lower case
     * @param Reach $reach Where the variable has a value
     * @param ValueShape $shape The values it takes
     * @param string|int|null $default The global value of a new server; for an unsigned variable, the 64 bits of the value read as a signed integer
     * @param Writability $writability Which values SET can change
     * @param int|null $minimum The smallest integer it takes, when bounded
     * @param int|null $maximum The largest integer it takes, when bounded; for an unsigned variable, the 64 bits of the value read as a signed integer
     * @param Domain $domain The type of a read
     */
    public function __construct(
        public readonly string $name,
        public readonly Reach $reach,
        public readonly ValueShape $shape,
        public readonly string|int|null $default,
        public readonly Writability $writability,
        public readonly ?int $minimum,
        public readonly ?int $maximum,
        public readonly Domain $domain,
    ) {
    }
}
