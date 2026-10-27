<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\User\Factor;
use SqlSemantics\Platform\MySql\Statement\Account\User\RegistrationStep;
use SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `ALTER USER [IF EXISTS] account n FACTOR {INITIATE REGISTRATION | FINISH REGISTRATION SET CHALLENGE_RESPONSE AS '…' | UNREGISTER}` (8.0.27+).
 *
 * Mirrors the registration flags of LEX_MFA. Rule: MYSQL-ALTER-REGISTRATION-001.
 * The challenge response is an operand with its exact value. The factor
 * type admits exactly the second and third authentication factors.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-registration. Status: Implemented.
 *
 * @visibility public
 * @example Starting the registration of a device
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('alter user user() 2 factor initiate registration')->toString() // => 'ALTER USER USER() 2 FACTOR INITIATE REGISTRATION'
 */
final class AlterRegistration extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Account|SessionUser $user The account
     * @param Factor $factor The additional authentication factor
     * @param RegistrationStep $step The registration step
     * @param Text|null $challengeResponse The challenge response of FINISH REGISTRATION
     */
    public function __construct(
        public readonly bool $ifExists,
        public readonly Account|SessionUser $user,
        public readonly Factor $factor,
        public readonly RegistrationStep $step,
        public readonly ?Text $challengeResponse = null,
        ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
    ) {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input(($challengeResponse !== null) === ($step === RegistrationStep::Finish), 'A challenge response is written exactly by FINISH REGISTRATION.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Declares no relation or result columns.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'USER');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->node($this->user)->spelled($this->factor->value)->keyword('FACTOR');
        match ($this->step) {
            RegistrationStep::Initiate => $out->keyword('INITIATE', 'REGISTRATION'),
            RegistrationStep::Unregister => $out->keyword('UNREGISTER'),
            RegistrationStep::Finish => $out->keyword('FINISH', 'REGISTRATION', 'SET', 'CHALLENGE_RESPONSE', 'AS')->node($this->challengeResponse),
        };
    }
}
