<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

/**
 * The step of FIDO device registration ALTER USER requests for a factor.
 *
 * `INITIATE REGISTRATION` returns a challenge, `FINISH REGISTRATION SET
 * CHALLENGE_RESPONSE AS '…'` completes it with the signed response, and
 * `UNREGISTER` removes the device.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html#alter-user-registration.
 *
 * @visibility public
 * @example Naming a step
 *     \SqlSemantics\Platform\MySql\Statement\Account\User\RegistrationStep::Finish->name // => 'Finish'
 */
enum RegistrationStep
{
    case Initiate;
    case Unregister;
    case Finish;
}
