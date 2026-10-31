<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Lowering\Lowering;

/**
 * Preserves encoding-name conditions before incomplete SET and conversion operands.
 * Only a reduced charset name is read; no parser node or input text is retained.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxCharsets
{
    /**
     * Reads a charset name after NAMES or USING before its enclosing construct completes.
     *
     * @param list<\SqlParser\Lexer\Token> $tokens The temporary input prefix
     */
    public function completed(Node $node, Lowering $lowering, array $tokens): \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure|null
    {
        $setting = (new \SqlSemantics\Platform\MySql\Lowering\Utility\Set\SyntaxEncoding())->completed($node, $lowering);
        if ($setting !== null) {
            return $setting;
        }
        if ($node->name !== 'charset_name') {
            return null;
        }
        $start = (new SourceRanges())->span($node)[0] ?? 0;
        $previous = null;
        foreach ($tokens as $token) {
            if ($token->offset >= $start) {
                break;
            }
            $previous = $token;
        }
        if (!in_array($previous?->name, ['NAMES_SYM', 'USING'], true)) {
            return null;
        }
        try {
            $name = $lowering->charsets->charset($node)->name;
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            return new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($failure->problem, true);
        }
        $notice = $name === null ? null : \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::charsetWarning($name->value);

        return $notice !== null && $notice->warnedIn($lowering->profile->grammar) ? $notice : null;
    }


}
