<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\DollarQuotedDomain;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;

/**
 * Implements sql_lex.cc/MY_LEX_IDENT_OR_DOLLAR_QUOTED_TEXT and get_dollar_quoted_text.
 * The tag is an identifier run excluding dollars; the first identical delimiter closes the text.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 */
final class DollarStringDefinitions
{
    /**
     * Lists the reviewed releases containing the dollar-quoted scanner state.
     * @return non-empty-list<string>
     */
    public function versions(): array
    {
        return ['mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'];
    }

    /**
     * Binds the scanner domain to releases that implement it.
     */
    public function create(string $version): LexemeGenerator
    {
        return new VersionedLexemeGenerator($version, new VersionCase(
            $this->versions(),
            new PatternLexemeGenerator(
                'DOLLAR_QUOTED_STRING_SYM',
                '~\A(\$[A-Za-z0-9_\x80-\xff]*\$)(?:(?!\1)[^\x00])*\1\z~Ds',
                ['$$text$$', '$tag$text$tag$'],
                'string',
                'sql/sql_lex.cc:get_dollar_quoted_text',
                new DollarQuotedDomain(
                    new CharacterDomain(str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_'), 0, 16),
                    new CharacterDomain([...array_map(chr(...), array_values(array_diff(range(1, 127), [36]))), 'é', '猫'], 0, 255),
                ),
            ),
            'mysql-dollar-quoted-strings',
        ));
    }
}
