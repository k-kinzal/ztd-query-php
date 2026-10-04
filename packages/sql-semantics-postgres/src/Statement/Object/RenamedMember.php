<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The column, constraint or attribute ALTER ... RENAME renames: `COLUMN a`, `CONSTRAINT c` or `ATTRIBUTE a`.
 *
 * Mirrors the `subname` of PostgreSQL's `RenameStmt`. The word COLUMN is
 * optional and always written back.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Reading the renamed column
 *     $member = new \SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedMember(\SqlSemantics\Platform\PostgreSql\Statement\Object\RenamedPart::Column, new \SqlSemantics\Statement\Identifier\Name('a'));
 *     [$member->part->value, $member->name->value] // => ['COLUMN', 'a']
 */
final class RenamedMember implements Node
{
    use Snapshot;

    /**
     * @param RenamedPart $part What kind of part is renamed
     * @param Name $name The current name of the part
     */
    public function __construct(public readonly RenamedPart $part, public readonly Name $name)
    {
    }

    /**
     * Writes the keyword and the current name.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->part->value)->name($this->name, NameUse::Column);
    }
}
