<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;

/**
 * `COMMENT [=] 'text'`: a comment on a tablespace or log file group.
 *
 * The equals sign is optional and not written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility public
 * @example Holding the option
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\CommentOption(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('cold')))->comment->value // => 'cold'
 */
final class CommentOption implements StorageOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Text $comment The comment
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(public readonly Text $comment, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = false)
    {
    }

    /**
     * Answers the keyword of the option.
     */
    public function keyword(): string
    {
        return 'COMMENT';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('COMMENT');
        if ($this->equals) {
            $out->symbol('=');
        }
        $out->node($this->comment);
    }
}
