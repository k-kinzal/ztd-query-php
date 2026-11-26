<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

/**
 * One password management or account locking option of CREATE USER or ALTER USER, with its number when it takes one.
 *
 * The number is kept as written. Several options may be written; for one
 * field the last option written wins in the server, so the list keeps every
 * option in source order.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-password-management.
 *
 * @visibility SqlSemantics
 */
final class AccountOption implements Node
{
    use Snapshot;

    /**
     * @param AccountOptionKind $kind The option
     * @param Numeral|null $number The number of an option that takes one
     */
    public function __construct(public readonly AccountOptionKind $kind, public readonly ?Numeral $number = null)
    {
        Check::input(($number !== null) === $kind->numbered(), 'A number is written exactly for an option that takes one.');
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword(...$this->kind->words())->node($this->number);
        $unit = $this->kind->unit();
        if ($unit !== null) {
            $out->keyword($unit);
        }
    }
}
