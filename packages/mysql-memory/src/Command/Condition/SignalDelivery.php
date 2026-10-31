<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Condition;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Diagnostics;

/**
 * Delivers an evaluated SIGNAL condition after its items and handler state are resolved.
 *
 * Class 01 records a warning and completes. Class 02 and other classes raise the
 * appropriate SQL error with the evaluated information items intact.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/signal.html.
 *
 * @visibility MySqlMemory
 */
final class SignalDelivery
{
    /**
     * Delivers the declared SQLSTATE as a warning completion or a terminal error.
     *
     * @param array<string, string> $signalled The evaluated condition information
     * @throws SqlError When the SQLSTATE is not in warning class 01
     */
    public function deliver(string $state, ?string $message, ?int $number, array $signalled, Diagnostics $diagnostics): Reply
    {
        $class = substr($state, 0, 2);
        $code = match ($class) {
            '01' => ProgramError::SignalWarning,
            '02' => ProgramError::SignalNotFound,
            default => ProgramError::SignalException,
        };
        if ($class === '01') {
            $diagnostics->signal($number ?? $code->value, $message ?? $code->message(), $signalled);

            return new Completion(0, 0, $diagnostics->raised());
        }

        throw new SqlError($code, $message ?? $code->message(), null, [], $signalled, $number);
    }

}
