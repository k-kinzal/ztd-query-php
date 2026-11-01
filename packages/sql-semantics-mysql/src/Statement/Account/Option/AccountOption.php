<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * One password management or account locking option of CREATE USER or ALTER USER, with its number when it takes one.
 *
 * Every written number is retained; integer-token and option-range conditions
 * belong to the containing request.
 * Several options may be written; for one
 * field the last option written wins in the server, so the list keeps every
 * option in source order.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-password-management.
 *
 * @visibility public
 * @example Reading a password history option
 *     $option = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE USER u PASSWORD HISTORY 5')->options[0];
 *     [$option->kind, $option->number?->text] // => [\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::HistoryCount, '5']
 * @example Retaining an invalid locking interval
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::LockTime, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('00032768')))->number?->text // => '00032768'
 * @example Retaining a fractional password history
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::HistoryCount, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1.0')))->number?->text // => '1.0'
 * @example Retaining a number beyond the unsigned integer domain
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::HistoryCount, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('18446744073709551616')))->number?->text // => '18446744073709551616'
 */
final class AccountOption implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param AccountOptionKind $kind The option
     * @param Numeral|null $number The number of an option that takes one
     */
    public function __construct(public readonly AccountOptionKind $kind, public readonly ?Numeral $number = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
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
