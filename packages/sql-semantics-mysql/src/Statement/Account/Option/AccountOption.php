<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\NumberChecks;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One password management or account locking option of CREATE USER or ALTER USER, with its number when it takes one.
 *
 * The number is an integer token within the option's range, kept as written.
 * Several options may be written; for one
 * field the last option written wins in the server, so the list keeps every
 * option in source order.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-password-management.
 *
 * @visibility public
 * @example Reading a password history option
 *     $option = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE USER u PASSWORD HISTORY 5')->statement->options[0];
 *     [$option->kind, $option->number?->text] // => [\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::HistoryCount, '5']
 * @example Refusing an intrinsically invalid locking interval
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::LockTime, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('00032768')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a fractional password history
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::HistoryCount, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1.0')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a decimal token above the unsigned integer domain
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::HistoryCount, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('18446744073709551616')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
        $problem = $number === null ? null : (new NumberChecks())->option($kind, $number);
        Check::input($problem === null, $problem?->message() ?? 'An account option has an integer in its permitted range.');
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
