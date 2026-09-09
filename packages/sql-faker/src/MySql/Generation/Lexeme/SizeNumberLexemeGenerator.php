<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use Override;
use SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;

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
        $prefix = new IntegerLexemeGenerator('SIZE_PREFIX', '0', '2147483647', ['1'], 'sql/sql_yacc.yy:size_number');
        $candidates = [];
        foreach ($input->requested === null ? ['1K', '1M', '1G'] : [$input->requested] as $spelling) {
            $value = str_starts_with($spelling, '`') && str_ends_with($spelling, '`') ? substr($spelling, 1, -1) : $spelling;
            if (preg_match('/\A([0-9]+)[kKmMgG]\z/D', $value, $matches) !== 1 || !$prefix->accepts($matches[1])) {
                continue;
            }
            $candidates[] = new LexemeSequence([
                new Lexeme($spelling, 'identifier', $input->terminal(), 'sql/sql_yacc.yy:size_number'),
            ], 'mysql.size-number:' . $spelling);
        }
        return LexemeCandidates::of(...$candidates);
    }
}
