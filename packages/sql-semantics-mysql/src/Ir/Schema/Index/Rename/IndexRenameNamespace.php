<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Closes the original and resulting physical namespaces of an index rename.
 * @visibility SqlSemantics
 */
final class IndexRenameNamespace
{
    /**
     * Validates the independent sets of initial and final index identities.
     * @param non-empty-list<IndexRename> $moves Resolved sources and final names
     * @param list<Identifier> $unmoved Physical names retained by the operation
     */
    public function check(array $moves, array $unmoved): void
    {
        Check::input(count($moves) + count($unmoved) <= 64, 'A native index namespace can contain at most 64 physical keys.');
        $case = new NativeIdentifierCase();
        $untouched = [];
        foreach ($unmoved as $key) {
            $name = $case->fold($key->value);
            Check::input(preg_match('/\A.{1,64}(?<! )\z/us', $key->value) === 1 && !isset($untouched[$name]), 'The unmoved native index names must be distinct and valid.');
            Check::input($name !== 'primary' || $key->value === 'PRIMARY', 'Only the native PRIMARY name supplies the physical primary key.');
            $untouched[$name] = true;
        }
        $original = $untouched;
        $result = $untouched;
        foreach ($moves as $move) {
            $before = $case->fold($move->source->value);
            $after = $case->fold($move->destination->value);
            Check::input(!isset($original[$before]), 'Each source must name a distinct original native index.');
            Check::input(!isset($result[$after]), 'The resulting native index names must be distinct.');
            $original[$before] = true;
            $result[$after] = true;
        }
    }
}
