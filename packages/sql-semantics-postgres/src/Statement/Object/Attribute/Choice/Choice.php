<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice;

/**
 * A closed set of words that a definition attribute accepts as its value.
 *
 * The command reads the value as text and compares it with the words it
 * knows; each set decides whether the comparison ignores case, as its command
 * does.
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html, https://www.postgresql.org/docs/17/sql-createaggregate.html,
 * https://www.postgresql.org/docs/17/sql-createcollation.html.
 *
 * @visibility public
 * @example Reading the word an alignment is written with
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Alignment::read('float8')?->name // => 'Double'
 */
interface Choice
{
    /**
     * Answers the member the text names, or null when the command rejects the text.
     */
    public static function read(string $text): ?self;
}
