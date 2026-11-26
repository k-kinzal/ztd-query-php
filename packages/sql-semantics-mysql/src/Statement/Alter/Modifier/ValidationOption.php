<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Modifier;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * `WITH VALIDATION` or `WITHOUT VALIDATION` (5.7 and later): whether the server checks rows against generated columns or an exchanged partition.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-management-exchange.html.
 *
 * @visibility SqlSemantics
 * @example Skipping the row validation
 *     (new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption(false))->validate // => false
 */
final class ValidationOption implements AlterModifier
{
    use Snapshot;

    /**
     * @param bool $validate Whether WITH (true) or WITHOUT (false) is written
     */
    public function __construct(public readonly bool $validate)
    {
    }

    /**
     * Derives nothing: the option holds no expression.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->validate ? 'WITH' : 'WITHOUT', 'VALIDATION');
    }
}
