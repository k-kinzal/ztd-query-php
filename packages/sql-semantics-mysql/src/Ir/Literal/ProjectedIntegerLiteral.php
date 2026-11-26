<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Literal;

use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A signed integer result whose digits determine its native display width.
 *
 * @visibility public
 * @example Separating the integer value from its visible digit extent
 *     (new \SqlSemantics\Platform\MySql\Ir\Literal\ProjectedIntegerLiteral('-00042'))->decimal() // => '-42'
 */
final class ProjectedIntegerLiteral
{
    use Snapshot;

    /**
     * Checks the exact token grammar and signed 64-bit value.
     */
    public function __construct(public readonly string $token)
    {
        Check::input(preg_match('/\A-?[0-9]+\z/', $token) === 1, 'A projected integer token needs decimal digits and an optional minus sign.');
        new RangeIntegerLiteral($this->decimal());
    }

    /**
     * Derives the canonical mathematical value.
     */
    public function decimal(): string
    {
        $magnitude = ltrim($this->token, '-0');
        if ($magnitude === '') {
            return '0';
        }

        return ($this->token[0] === '-' ? '-' : '') . $magnitude;
    }

    /**
     * Emits the validated digits and polarity.
     */
    public function toString(): string
    {
        return $this->token;
    }
}
