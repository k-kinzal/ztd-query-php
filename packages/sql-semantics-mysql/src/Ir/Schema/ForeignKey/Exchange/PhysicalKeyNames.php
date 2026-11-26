<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\IndexNameAllocation;

/**
 * Allocates effective physical names in the single native action order.
 * @visibility SqlSemantics
 */
final class PhysicalKeyNames
{
    /**
     * Allocates all surviving physical additions after removing superseded implicit names.
     * @param array<array-key, ForeignKeyNativeKey> $occupied Original keys by folded physical name
     * @param array<array-key, Identifier> $replaced Replaced original keys by folded name
     * @param array<int, array{Identifier, Identifier|null}> $requests Leading field and optional requested name, keyed by action position
     * @return array<int, Identifier>
     */
    public function allocate(array $occupied, array $replaced, array $requests, GrammarRelease $release): array
    {
        $used = [];
        foreach (array_keys($occupied) as $name) {
            if (!isset($replaced[$name])) {
                $used[$name] = true;
            }
        }
        ksort($requests, SORT_NUMERIC);
        $case = new NativeIdentifierCase();
        $allocator = new IndexNameAllocation();
        $result = [];
        foreach ($requests as $position => [$leading, $label]) {
            $name = $label->value ?? $allocator->available($leading->value, $used, $release);
            Check::input($name !== null && preg_match('/\A.{1,64}(?<! )\z/us', $name) === 1, 'An introduced foreign-key index needs a valid physical name.');
            $key = $case->fold($name);
            Check::input($key !== 'primary' && !isset($used[$key]), 'An introduced foreign-key index needs an available physical name.');
            $used[$key] = true;
            $result[$position] = new Identifier($name);
        }
        Check::input(count($used) <= 64, 'Native foreign-key additions must fit the physical index limit.');

        return $result;
    }
}
