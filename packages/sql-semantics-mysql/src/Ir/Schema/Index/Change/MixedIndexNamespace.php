<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Change;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRenameNamespace;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Verifies the original physical namespace and its resulting occupied names.
 * @visibility SqlSemantics
 */
final class MixedIndexNamespace
{
    /**
     * Closes all original names and the referenced generating support.
     * @param non-empty-list<IndexIdentity> $drops Checked removals
     * @param non-empty-list<IndexRename> $moves Checked renames
     * @param list<Identifier> $unmoved Physical names preserved without a change
     */
    public function check(array $drops, array $moves, array $unmoved): void
    {
        (new IndexRenameNamespace())->check($moves, $unmoved);
        Check::input(count($drops) + count($moves) + count($unmoved) <= 64, 'A native index namespace can contain at most 64 physical keys.');
        $case = new NativeIdentifierCase();
        $original = [];
        foreach ($moves as $move) {
            $original[$case->fold($move->source->value)] = true;
        }
        foreach ($unmoved as $prior) {
            $original[$case->fold($prior->value)] = true;
        }
        foreach ($drops as $drop) {
            $name = $case->fold($drop->name->value);
            Check::input(!isset($original[$name]), 'A removed index cannot also be renamed or unmoved.');
            $original[$name] = true;
        }
        foreach ($drops as $drop) {
            foreach ($drop->autoIncrement->alternates ?? [] as $alternate) {
                Check::input(isset($original[$case->fold($alternate->value)]), 'Every AUTO_INCREMENT backing name must belong to the original native namespace.');
            }
        }
    }
}
