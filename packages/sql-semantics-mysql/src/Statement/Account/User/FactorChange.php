<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `user ADD|MODIFY|DROP n FACTOR … [ADD|MODIFY|DROP m FACTOR …]`: an ALTER USER change of authentication factors.
 *
 * Mirrors the add_factor, modify_factor and drop_factor flags of LEX_MFA.
 * One or two factors are changed with the same action; ADD and MODIFY give
 * each factor a method, DROP gives none. Naming the same factor twice
 * (ER_MFA_METHODS_IDENTICAL) or adding 3 before 2
 * (ER_MFA_METHODS_INVALID_ORDER) is reported by the statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-multifactor.
 *
 * @visibility public
 * @example Dropping the second factor
 *     $change = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER USER u DROP 2 FACTOR')->statement->users[0];
 *     $change->action // => \SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction::Drop
 */
final class FactorChange implements UserAlteration
{
    use Snapshot;

    /**
     * @var list<FactorStep> The changed factors in order; one or two
     */
    public readonly array $steps;

    /**
     * @param Account $user The account
     * @param FactorAction $action What is done to each factor
     * @param list<FactorStep> $steps The changed factors in order; one or two
     */
    public function __construct(public readonly Account $user, public readonly FactorAction $action, array $steps)
    {
        $this->steps = Check::listOf($steps, FactorStep::class, 'A factor change names one or two factors.', 1);
        Check::input(count($this->steps) <= 2, 'A factor change names one or two factors.');
        foreach ($this->steps as $step) {
            Check::input(($step->identification === null) === ($action === FactorAction::Drop), 'ADD and MODIFY give a factor a method; DROP gives none.');
        }
    }

    /**
     * Writes the account and each change.
     */
    public function render(Output $out): void
    {
        $out->node($this->user);
        foreach ($this->steps as $step) {
            $out->keyword($this->action->value)->node($step);
        }
    }
}
