<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableChange;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Checks the ALGORITHM and LOCK names of online data definition statements against the release.
 *
 * Rule: MYSQL-ALTER-CHOICES-001. The server compares the name without
 * regard to case with the values of its release: ALGORITHM INPLACE and COPY,
 * INSTANT from 8.0 on, and DEFAULT as a name in 5.6 and 5.7 only; LOCK
 * NONE, SHARED and EXCLUSIVE, and DEFAULT as a name in 5.6 and 5.7 only.
 * The keyword DEFAULT is always accepted. Any other name is the diagnostic
 * UnknownAlterChoice. Terminates: constant work.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_alter.cc (Alter_info::set_requested_algorithm,
 * set_requested_lock), https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 * (alter_algorithm_option_value, alter_lock_option_value).
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Choices
{
    /**
     * Reports an ALGORITHM name the release does not know.
     */
    public function algorithm(?Name $name, Derivation $derivation): void
    {
        $this->check($name, ['INPLACE', 'COPY', ...($this->legacy($derivation) ? ['DEFAULT'] : ['INSTANT'])], false, $derivation);
    }

    /**
     * Reports a LOCK name the release does not know.
     */
    public function lock(?Name $name, Derivation $derivation): void
    {
        $this->check($name, ['NONE', 'SHARED', 'EXCLUSIVE', ...($this->legacy($derivation) ? ['DEFAULT'] : [])], true, $derivation);
    }

    /**
     * Tells whether the release is 5.6 or 5.7.
     */
    public function legacy(Derivation $derivation): bool
    {
        $grammar = $derivation->context->profile->grammar;

        return $grammar === GrammarRelease::MySql5651 || $grammar === GrammarRelease::MySql5744;
    }

    /**
     * Reports a name that is none of the known values.
     *
     * @param list<string> $known The values the server accepts, in upper case
     */
    public function check(?Name $name, array $known, bool $lock, Derivation $derivation): void
    {
        if ($name !== null && !in_array(strtoupper($name->value), $known, true)) {
            $derivation->report(new UnknownAlterChoice($lock, $name));
        }
    }
}
