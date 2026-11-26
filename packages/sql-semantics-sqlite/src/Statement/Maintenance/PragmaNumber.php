<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Maintenance;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;

/**
 * A numeric grammar token passed to a native pragma as text.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class PragmaNumber implements Node
{
    use Snapshot;

    /**
     * Keeps the checked number and its written sign until the pragma resolves its setting.
     */
    public function __construct(public readonly ?NumberSign $sign, public readonly string $token)
    {
        Check::input(preg_match('/\A(?:0[xX][0-9A-Fa-f]+|(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)\z/', $token) === 1, 'A pragma numeric value must be a single SQLite number.');
    }

    /**
     * Supplies the argument text SQLite passes to the selected native operation.
     */
    public function nativeText(): string
    {
        return ($this->sign === NumberSign::Minus ? '-' : '') . $this->token;
    }

    /**
     * Writes the signed number in its grammar position.
     */
    public function render(Output $out): void
    {
        if ($this->sign !== null) {
            $out->symbol($this->sign->value)->glue();
        }
        $out->spelled($this->token);
    }
}
