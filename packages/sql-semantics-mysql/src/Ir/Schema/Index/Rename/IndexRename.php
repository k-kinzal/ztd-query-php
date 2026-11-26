<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A physical index name in the original namespace and its new name.
 *
 * The containing alteration binds both names to one owning table.
 * @visibility public
 * @example Replacing a physical index label
 *     $identifier = \SqlSemantics\Platform\MySql\Ir\Identifier::class;
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename(new $identifier('legacy'), new $identifier('by_email')))->destination->value // => 'by_email'
 */
final class IndexRename
{
    use Snapshot;

    /**
     * Checks the source and destination as renamable native names.
     */
    public function __construct(public readonly Identifier $source, public readonly Identifier $destination)
    {
        $case = new NativeIdentifierCase();
        foreach ([$source, $destination] as $name) {
            Check::input(preg_match('/\A.{1,64}(?<! )\z/us', $name->value) === 1, 'An index name has 1 to 64 characters and no trailing space.');
            Check::input($case->fold($name->value) !== 'primary', 'A native index rename cannot name the physical primary key.');
        }
    }

    /**
     * Emits one relative index action in the owning ALTER TABLE.
     */
    public function toString(): string
    {
        return 'RENAME INDEX ' . $this->source->quoted() . ' TO ' . $this->destination->quoted();
    }
}
