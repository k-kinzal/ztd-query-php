<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use Override;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\MySql\Generation\Value\QuotedDomain;

/**
 * sql_yacc.yy:filter_wild_db_table_string requires a dot and forbids line feeds after get_text decoding.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class ReplicationTablePatternLexemeGenerator implements LexemeGenerator
{
    /**
     * Keeps the original quoted spelling while validating the source action's decoded string conditions.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        if ($input->terminal()->name !== 'REPLICATION_TABLE_PATTERN') {
            return null;
        }
        $candidates = [];
        foreach ($input->requested === null ? ["'db.table'", "'db.%'", "'%.table'"] : [$input->requested] as $spelling) {
            if ($this->accepts($spelling)) {
                $candidates[] = new LexemeSequence([
                    new Lexeme($spelling, 'string', $input->terminal(), 'sql/sql_yacc.yy:filter_wild_db_table_string'),
                ], 'mysql.replication-table-pattern:' . $spelling);
            }
        }
        return LexemeCandidates::of(...$candidates);
    }

    /**
     * Decodes escapes affecting dot, newline and NUL checks; strcont examines the C-string prefix.
     */
    public function accepts(string $spelling): bool
    {
        $domain = new QuotedDomain("'", backslash: true);
        if (!in_array(strlen($spelling), $domain->match($spelling), true)) {
            return false;
        }
        $decoded = '';
        for ($index = 1; $index < strlen($spelling) - 1; ++$index) {
            $character = $spelling[$index];
            if ($character === '\\') {
                $character = match ($spelling[++$index]) {
                    'n' => "\n", '0' => "\0", default => $spelling[$index],
                };
            } elseif ($character === "'" && ($spelling[$index + 1] ?? null) === "'") {
                ++$index;
            }
            $decoded .= $character;
        }
        $prefix = explode("\0", $decoded)[0];
        return str_contains($prefix, '.') && !str_contains($prefix, "\n");
    }
}
