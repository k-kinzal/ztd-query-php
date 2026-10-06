<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The `ATTRIBUTE '…'` or `COMMENT '…'` clause of CREATE USER or ALTER USER.
 *
 * An attribute must be a JSON object; the server rejects anything else
 * (ER_INVALID_USER_ATTRIBUTE_JSON), which the statement reports.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-comments-attributes.
 *
 * @visibility public
 * @example Reading a comment
 *     $comment = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE USER u COMMENT 'ops'")->statement->comment;
 *     [$comment?->kind, $comment?->text->value] // => [\SqlSemantics\Platform\MySql\Statement\Account\Option\CommentKind::Comment, 'ops']
 */
final class UserComment implements Node
{
    use Snapshot;

    /**
     * @param CommentKind $kind Whether the attributes or the comment are set
     * @param Text $text The JSON object or the comment
     */
    public function __construct(public readonly CommentKind $kind, public readonly Text $text)
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->text);
    }
}
