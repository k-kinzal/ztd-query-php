<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An index repeats a column, independently of duplicate table column declarations.
 *
 * This ownership distinction preserves key validation after declaration validation.
 *
 * @visibility public
 * @example Reading the resolved key condition
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\DuplicateKeyColumn(new \SqlSemantics\Statement\Identifier\Name('a')))->message() // => 'Duplicate key column a.'
 */
final class DuplicateKeyColumn implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The repeated key-column occurrence
     */
    public function __construct(public readonly Name $column)
    {
    }

    /**
     * Describes the repeated column of the key.
     */
    public function message(): string
    {
        return 'Duplicate key column ' . $this->column->value . '.';
    }
}
