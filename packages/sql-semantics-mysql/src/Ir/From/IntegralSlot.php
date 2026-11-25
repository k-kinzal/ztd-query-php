<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A native signed field with an explicit integral storage width and NULL policy.
 *
 * @visibility public
 * @example Inspecting the exact signed storage width
 *     (new \SqlSemantics\Platform\MySql\Ir\From\IntegralSlot(\SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::Wide, true))->storage->value // => 'BIGINT'
 */
final class IntegralSlot
{
    use Snapshot;

    /**
     * Declares one of the native integral widths with its resolved NULL policy.
     */
    public function __construct(public readonly IntegerStorage $storage, public readonly bool $nullable)
    {
        Check::input($storage !== IntegerStorage::Standard, 'An ordinary INT field uses the native signed INT role.');
    }
}
