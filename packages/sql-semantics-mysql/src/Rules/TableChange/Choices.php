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
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-performance.
 * Release distinctions and refusal order are checked through native SQL observations.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Choices
{
    /**
     * Identifies names that belong to the option domain in at least one supported release.
     */
    public function known(?Name $name, bool $lock): bool
    {
        return $name === null || in_array(strtoupper($name->value), $lock ? ['DEFAULT', 'NONE', 'SHARED', 'EXCLUSIVE'] : ['DEFAULT', 'INPLACE', 'COPY', 'INSTANT'], true);
    }

    /**
     * Identifies an option name outside the selected release without discarding its value.
     */
    public function refusal(?Name $name, bool $lock, GrammarRelease $grammar): ?UnknownAlterChoice
    {
        if ($name === null) {
            return null;
        }
        $legacy = in_array($grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $value = strtoupper($name->value);
        return !$this->known($name, $lock) || ($value === 'DEFAULT' && !$legacy) || (!$lock && $value === 'INSTANT' && $legacy) ? new UnknownAlterChoice($lock, $name) : null;
    }

    /**
     * Reports an ALGORITHM name the release does not know.
     */
    public function algorithm(?Name $name, Derivation $derivation, ?\SqlSemantics\Statement\Node $owner = null): void
    {
        $problem = $this->refusal($name, false, $derivation->context->profile->grammar);
        if ($problem !== null) {
            $derivation->report($problem);
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $owner);
        }
    }

    /**
     * Reports a LOCK name the release does not know.
     */
    public function lock(?Name $name, Derivation $derivation, ?\SqlSemantics\Statement\Node $owner = null): void
    {
        $problem = $this->refusal($name, true, $derivation->context->profile->grammar);
        if ($problem !== null) {
            $derivation->report($problem);
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $owner);
        }
    }

}
