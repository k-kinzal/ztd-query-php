<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\IndexKeyCoverage;

/**
 * Derives physical foreign-key key alternatives from the native placement rules.
 * @visibility SqlSemantics
 */
final class IndexDependencyKeys
{
    /**
     * Computes the support set under the original and resulting physical layouts.
     * @param non-empty-list<Name> $fields Ordered fields belonging to the resolved table
     * @param list<IndexDeclaration> $initial Settled keys before the statement
     * @param list<IndexDeclaration> $remaining Settled keys that keep their physical definitions
     * @return list<Identifier>
     */
    public function support(array $fields, bool $parent, GrammarRelease $release, array $initial, array $remaining): array
    {
        [$exactUnique, $hidden] = $this->policy($parent, $release);
        $coverage = new IndexKeyCoverage();
        $before = $hidden ? $coverage->cluster($initial) : null;
        $after = $hidden ? $coverage->cluster($remaining) : null;
        $result = [];
        foreach ($initial as $index) {
            $survives = in_array($index, $remaining, true);
            if ($coverage->covers($fields, $index, $survives ? $after : $before, $exactUnique)) {
                $result[] = new Identifier($index->name->value);
            }
        }

        return $result;
    }

    /**
     * Determines exact uniqueness and implicit clustered-field eligibility.
     * @return array{bool, bool}
     */
    public function policy(bool $parent, GrammarRelease $release): array
    {
        return match ($release) {
            GrammarRelease::MySql5651, GrammarRelease::MySql5744 => [false, true],
            GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830 => [false, $parent],
            GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910 => [$parent, false],
        };
    }
}
