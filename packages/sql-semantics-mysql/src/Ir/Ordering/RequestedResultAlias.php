<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Ordering;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An explicit alias whose requested bytes affect native diagnostics.
 *
 * @visibility public
 * @example Reading the native heading of an alias with leading space
 *     (new \SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias(' amount'))->value // => 'amount'
 */
final class RequestedResultAlias
{
    use Snapshot;

    /**
     * The label returned for the ordered native result column.
     */
    public readonly string $value;

    /**
     * Establishes the requested spelling and the effective heading.
     */
    public function __construct(public readonly string $requested)
    {
        $native = (new AliasHeading())->normalize($requested);
        Check::input($requested !== $native, 'An unchanged result alias uses its effective identifier directly.');
        $this->value = $native;
    }

    /**
     * Gives the same name key as the corresponding effective identifier.
     */
    public function key(): string
    {
        return Comparison::AsciiInsensitive->fold($this->value);
    }

    /**
     * Quotes the exact requested alias for the alias clause.
     */
    public function aliasSql(): string
    {
        return '`' . str_replace('`', '``', $this->requested) . '`';
    }
}
