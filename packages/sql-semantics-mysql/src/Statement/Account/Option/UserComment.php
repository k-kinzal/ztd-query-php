<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Option;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * The `ATTRIBUTE '…'` or `COMMENT '…'` clause of CREATE USER or ALTER USER.
 *
 * An attribute must be a JSON object; the server rejects anything else
 * (ER_INVALID_USER_ATTRIBUTE_JSON), which the statement reports.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-comments-attributes.
 *
 * @visibility public
 * @example Reading a comment
 *     $comment = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE USER u COMMENT 'ops'")->comment;
 *     [$comment?->kind, $comment?->text->value] // => [\SqlSemantics\Platform\MySql\Statement\Account\Option\CommentKind::Comment, 'ops']
 */
final class UserComment implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param CommentKind $kind Whether the attributes or the comment are set
     * @param Text $text The JSON object or the comment
     */
    public function __construct(public readonly CommentKind $kind, public readonly Text $text, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($text->radix === null, 'An account comment or attribute is quoted text.');
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->text);
    }
}
