<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;

/**
 * Intersects native signature domains with the language requirements of a structure.
 *
 * X() can name a stored routine only after its native spatial name is removed.
 * REGEXP_LIKE() can name a stored routine only before that native function is
 * introduced. Combining those calls, or adding an incompatible grammar feature,
 * leaves no language interpretation. Routine availability remains a state concern.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/function-resolution.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Signatures
{
    /**
     * Finds a common language for all calls and the enclosing grammar requirements.
     *
     * @param list<FunctionCall|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall> $calls The calls retained in one structure
     * @param int $generation Legacy (1), modern (2), or unrestricted (0) forms
     * @param int $releases The intersected domains of release-specific meanings
     * @param int $transition MySQL 5.6-only (1), 5.7-or-later (2), or unrestricted (0) forms
     */
    public function compatible(array $calls, int $generation, int $transition, ?LanguageProfile $spelling, int $releases = ReleaseDomain::ALL): bool
    {
        if ($calls === []) {
            return true;
        }
        $natives = new NativeFunctions();
        foreach (GrammarRelease::cases() as $release) {
            if (((new ReleaseDomain())->bit($release) & $releases) === 0 || ($spelling !== null && $spelling->grammar !== $release)) {
                continue;
            }
            $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
            if (($generation === 1 && !$legacy) || ($generation === 2 && $legacy) || ($transition === 1 && $release !== GrammarRelease::MySql5651) || ($transition === 2 && $release === GrammarRelease::MySql5651)) {
                continue;
            }
            $accepted = true;
            foreach ($calls as $call) {
                if ($call->schema === null && $natives->problem($release, $call->name, count($call->arguments), $call->named()) !== null) {
                    $accepted = false;
                    break;
                }
            }
            if ($accepted) {
                return true;
            }
        }

        return false;
    }
}
