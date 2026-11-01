<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\SchemaError;

/**
 * Formats versioned precision diagnostics already supplied by SQL Semantics.
 *
 * Accepted cast structures enforce their intrinsic precision at construction.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
 *
 * @visibility MySqlMemory
 */
final class Precision
{
    /**
     * Answers an error as the release reports it: MySQL 5.6 writes ER_TOO_BIG_PRECISION as "Too big precision ... for column ..." (verified on a live 5.6.51 server); any other error, or release, is answered as it is.
     */
    public function legacy(\MySqlMemory\Error\SqlError $error, \SqlSemantics\Contract\GrammarRelease $release): \MySqlMemory\Error\SqlError
    {
        if ($release !== \SqlSemantics\Contract\GrammarRelease::MySql5651 || $error->error !== SchemaError::TooBigPrecision || preg_match("/\\AToo-big precision (-?\\d+) specified for '(.*)'\\. Maximum is (\\d+)\\.\\z/s", $error->getMessage(), $match) !== 1) {
            return $error;
        }

        return new \MySqlMemory\Error\SqlError($error->error, "Too big precision {$match[1]} specified for column '{$match[2]}'. Maximum is {$match[3]}.", $error->getPrevious(), $error->following, $error->signalled, null, $error->recorded);
    }
}
