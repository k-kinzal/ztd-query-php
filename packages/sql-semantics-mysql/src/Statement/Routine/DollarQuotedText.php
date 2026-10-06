<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A dollar-quoted string: `$tag$ text $tag$` (MySQL 8.1 and later).
 *
 * The text between the delimiters is taken as it is, without escapes; the
 * server treats it as the opaque code of an external language. The tag is
 * part of the spelling the text needs: the text cannot hold its own closing
 * delimiter.
 * Source: https://dev.mysql.com/doc/refman/9.1/en/create-procedure.html.
 *
 * @visibility public
 * @example Holding dollar-quoted code
 *     $quoted = new \SqlSemantics\Platform\MySql\Statement\Routine\DollarQuotedText('return 1', 'js');
 *     [$quoted->tag, $quoted->text] // => ['js', 'return 1']
 */
final class DollarQuotedText implements Node
{
    use Snapshot;

    /**
     * @param string $text The text between the delimiters
     * @param string $tag The tag between the dollar signs of each delimiter; may be empty
     */
    public function __construct(public readonly string $text, public readonly string $tag = '')
    {
        Check::input(preg_match('/\A[^$\s]*\z/', $tag) === 1, 'The tag of a dollar-quoted string holds no dollar sign and no space.');
        Check::input(!str_contains($text, '$' . $tag . '$'), 'The text of a dollar-quoted string cannot hold its closing delimiter.');
    }

    /**
     * Writes the delimiters around the text.
     */
    public function render(Output $out): void
    {
        $out->spelled('$' . $this->tag . '$' . $this->text . '$' . $this->tag . '$');
    }
}
