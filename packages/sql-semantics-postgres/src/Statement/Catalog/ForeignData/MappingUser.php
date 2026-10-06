<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData;

use SqlSemantics\Rendering\Output;

/**
 * The keyword USER naming the user of a user mapping: the current user.
 *
 * The grammar reads USER here as CURRENT_USER; the spelling is kept.
 * Source: https://www.postgresql.org/docs/17/sql-createusermapping.html.
 *
 * @visibility public
 * @example Spelling the keyword
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\MappingUser::User->value // => 'USER'
 */
enum MappingUser: string implements \SqlSemantics\Statement\Node
{
    case User = 'USER';

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->value);
    }
}
