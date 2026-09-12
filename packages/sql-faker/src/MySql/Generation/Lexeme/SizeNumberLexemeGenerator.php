<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use Override;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;
use SqlFaker\Generation\Value\IntegerDomain;
use SqlFaker\Generation\Value\SequenceDomain;
use SqlFaker\Generation\Value\WordDomain;

/**
 * Supplies identifier-form sizes accepted by sql_yacc.yy:size_number, with a 31-bit magnitude prefix.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class SizeNumberLexemeGenerator implements LexemeGenerator
{
    /**
     * Preserves explicit decimal K/M/G spellings and their optional identifier quoting.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        if ($input->terminal()->name !== 'SIZE_NUMBER') {
            return null;
        }
        $domain = new SequenceDomain(new IntegerDomain('0', '2147483647'), new WordDomain(['K', 'M', 'G'], true));
        $candidates = [];
        foreach ($input->requested === null ? ['1K', '1M', '1G'] : [$input->requested] as $spelling) {
            $value = str_starts_with($spelling, '`') && str_ends_with($spelling, '`') ? substr($spelling, 1, -1) : $spelling;
            if (!in_array(strlen($value), $domain->match($value), true)) {
                continue;
            }
            $candidates[] = new LexemeSequence([
                new Lexeme($spelling, 'identifier', $input->terminal(), 'sql/sql_yacc.yy:size_number'),
            ], 'mysql.size-number:' . $spelling);
        }
        return LexemeCandidates::of(...$candidates);
    }
}
