<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `SET PASSWORD [FOR account] {= 'password' | TO RANDOM} [REPLACE 'current'] [RETAIN CURRENT PASSWORD]`: a request to set an account's password.
 *
 * Mirrors PT_option_value_no_option_type_password and its `_for` form
 * (8.0), and set_var_password (5.x). Rule: MYSQL-SET-PASSWORD-001. Without
 * FOR the session's own account is changed. The password is an operand with
 * its exact value; TO RANDOM (8.0.18+) asks the server to generate one and
 * return it as a result row. MySQL 5.x writes PASSWORD() or OLD_PASSWORD()
 * around the string (Password).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-password.html,
 * https://dev.mysql.com/doc/refman/5.7/en/set-password.html. Status: Implemented.
 *
 * @visibility public
 * @example Setting the password of another account
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("set password for app = 'pw' retain current password")->toString() // => "SET PASSWORD FOR app = 'pw' RETAIN CURRENT PASSWORD"
 */
final class SetPassword extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Account|null $user The account of FOR, when written
     * @param Password|null $password The password; null for TO RANDOM
     * @param Text|null $replace The current password REPLACE names
     * @param bool $retainCurrent Whether RETAIN CURRENT PASSWORD is written
     */
    public function __construct(public readonly ?Account $user, public readonly ?Password $password, public readonly ?Text $replace = null, public readonly bool $retainCurrent = false, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input($replace === null || $replace->radix === null, 'A replaced password is quoted text.');
        Check::input($password?->function === null || ($replace === null && !$retainCurrent), 'A password written with a function has no REPLACE and no RETAIN.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the retained password function notice.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $this->password?->derivePassword($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET', 'PASSWORD');
        if ($this->user !== null) {
            $out->keyword('FOR')->node($this->user);
        }
        if ($this->password === null) {
            $out->keyword('TO', 'RANDOM');
        } else {
            $out->symbol('=')->node($this->password);
        }
        if ($this->replace !== null) {
            $out->keyword('REPLACE')->node($this->replace);
        }
        if ($this->retainCurrent) {
            $out->keyword('RETAIN', 'CURRENT', 'PASSWORD');
        }
    }
}
