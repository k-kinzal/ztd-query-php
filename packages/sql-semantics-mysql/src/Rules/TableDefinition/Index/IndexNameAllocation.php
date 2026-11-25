<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Assigns names to the physical indexes of a native table declaration.
 *
 * @visibility SqlSemantics
 */
final class IndexNameAllocation
{
    /**
     * Returns an unused name derived from the declared first column.
     * @param array<array-key, IndexDeclaration> $used Occupied native case keys
     */
    public function generated(Name $first, array $used, GrammarRelease $release): ?Name
    {
        $case = new NativeIdentifierCase();
        $key = $case->fold($first->value);
        if ($key !== 'primary' && !isset($used[$key])) {
            return $first;
        }

        $bytes = match ($release) {
            GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql810,
            GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql901 => 30,
            GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910 => 61,
        };
        $prefix = substr($first->value, 0, $bytes);
        if (preg_match('//u', $prefix) !== 1) {
            return null;
        }
        for ($suffix = 2; $suffix < 100; ++$suffix) {
            $candidate = $prefix . '_' . $suffix;
            if (!isset($used[$case->fold($candidate)])) {
                return new Name($candidate);
            }
        }

        return null;
    }
}
