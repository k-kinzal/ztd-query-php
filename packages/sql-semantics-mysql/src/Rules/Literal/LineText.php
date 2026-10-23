<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Literal;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * The decoded domain of system text operands that prohibit line feeds.
 *
 * MySQL checks the prefix before the first NUL. Other controls and bytes after
 * that boundary remain part of the literal for rendering. This rule is local
 * to those operand roles; ordinary SQL strings can contain line feeds.
 * Native observations: MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LineText
{
    /**
     * Answers whether the effective system-text prefix has no line feed.
     */
    public function valid(Text $text): bool
    {
        return !str_contains(explode("\0", $text->bytes(), 2)[0], "\n");
    }
}
