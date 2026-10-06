<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\NumberChecks;
use SqlSemantics\Platform\MySql\Statement\Account\User\RegistrationStep;
use SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER USER [IF EXISTS] account n FACTOR {INITIATE REGISTRATION | FINISH REGISTRATION SET CHALLENGE_RESPONSE AS '…' | UNREGISTER}` (8.0.27+).
 *
 * Mirrors the registration flags of LEX_MFA. Rule: MYSQL-ALTER-REGISTRATION-001.
 * The challenge response is an operand with its exact value. A factor
 * number other than 2 or 3 is reported (MYSQL-ACCOUNT-NUMBER-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-registration. Status: Implemented.
 *
 * @visibility public
 * @example Starting the registration of a device
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('alter user user() 2 factor initiate registration')->toString() // => 'ALTER USER USER() 2 FACTOR INITIATE REGISTRATION'
 */
final class AlterRegistration implements Statement
{
    use Snapshot;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Account|SessionUser $user The account
     * @param Numeral $factor The factor number as written
     * @param RegistrationStep $step The registration step
     * @param Text|null $challengeResponse The challenge response of FINISH REGISTRATION
     */
    public function __construct(
        public readonly bool $ifExists,
        public readonly Account|SessionUser $user,
        public readonly Numeral $factor,
        public readonly RegistrationStep $step,
        public readonly ?Text $challengeResponse = null,
    ) {
        Check::input(($challengeResponse !== null) === ($step === RegistrationStep::Finish), 'A challenge response is written exactly by FINISH REGISTRATION.');
    }

    /**
     * Reports a factor number the server rejects.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new NumberChecks())->factor($derivation, $this->factor);
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
        $out->node($this->user)->node($this->factor)->keyword('FACTOR');
        match ($this->step) {
            RegistrationStep::Initiate => $out->keyword('INITIATE', 'REGISTRATION'),
            RegistrationStep::Unregister => $out->keyword('UNREGISTER'),
            RegistrationStep::Finish => $out->keyword('FINISH', 'REGISTRATION', 'SET', 'CHALLENGE_RESPONSE', 'AS')->node($this->challengeResponse),
        };
    }
}
