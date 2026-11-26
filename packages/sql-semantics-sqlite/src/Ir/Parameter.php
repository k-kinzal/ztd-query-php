<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A value supplied at a numbered SQLite parameter slot.
 *
 * @visibility public
 * @example Separating the marker label from its bound slot
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Parameter('002'))->slot // => 2
 */
final class Parameter
{
    use Snapshot;

    /**
     * The one-based SQLite binding slot.
     */
    public readonly int $slot;

    /**
     * The label preserves SQLite's binding name when labels share a slot.
     */
    public function __construct(public readonly string $label)
    {
        Check::input(preg_match('/\A[0-9]+\z/', $label) === 1, 'Expected a numbered parameter label.');
        $digits = ltrim($label, '0');
        Check::input(
            $digits !== '' && strlen($digits) <= 5 && (int) $digits <= 32766,
            'Parameter slot must be between 1 and 32766.',
        );
        $this->slot = (int) $digits;
    }
}
