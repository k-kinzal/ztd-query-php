<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Updates the selected pager threshold and the connection spill switch together.
 *
 * @visibility public
 * @example Enabling a 256-page threshold
 *     $amount = new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheAmount(256);
 *     $mode = \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillMode::Enabled;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillThreshold($amount, $mode))->sqlArgument() // => "'+256'"
 */
final class SpillThreshold
{
    use Snapshot;

    /**
     * Accepts exactly the combinations realizable by a nonzero native threshold operand.
     * @throws InvalidConstruction When a zero threshold or an impossible disabled combination is requested
     */
    public function __construct(public readonly CacheAmount $amount, public readonly SpillMode $mode)
    {
        $value = $amount->value;
        Check::input($value !== 0, 'A spill threshold update needs a nonzero quantity.');
        Check::input($mode === SpillMode::Enabled || ($value > 0 && ($value & 255) === 0), 'A disabled spill threshold needs a positive multiple of 256.');
    }

    /**
     * Emits a numeric argument whose prefix and switch interpretation match the checked pair.
     */
    public function sqlArgument(): string
    {
        $value = $this->amount->value;

        return $this->mode === SpillMode::Enabled && $value > 0 && ($value & 255) === 0
            ? "'+" . $value . "'"
            : (string) $value;
    }
}
