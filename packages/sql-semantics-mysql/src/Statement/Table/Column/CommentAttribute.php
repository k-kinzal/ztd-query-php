<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * The COMMENT of a column.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility SqlSemantics
 */
final class CommentAttribute implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param Text $comment The comment
     */
    public function __construct(public readonly Text $comment)
    {
    }

    /**
     * Derives nothing: the attribute holds no expression.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the attribute.
     */
    public function render(Output $out): void
    {
        $out->keyword('COMMENT')->node($this->comment);
    }
}
