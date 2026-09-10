<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator;
use SqlFaker\Grammar\Generation\Value\WordDomain;

/**
 * sql_yacc.yy/factor accepts exactly 2 or 3; paired ALTER USER factors differ and ADD factors ascend.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class FactorLexemeGenerator implements LexemeGenerator
{
    /**
     * Constrains the left factor using the resolved right factor within the same ALTER USER item.
     */
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        if ($input->terminal()->name !== 'AUTH_FACTOR_NUMBER') {
            return null;
        }
        $owner = $input->terminal()->ancestor('alter_user');
        $values = ['2', '3'];
        if ($owner !== null) {
            foreach ($input->right->parts as $part) {
                if ($part->lexeme->origin->name === 'AUTH_FACTOR_NUMBER' && $part->lexeme->origin->ancestor('alter_user') === $owner) {
                    $values = [$part->lexeme->text === '2' ? '3' : '2'];
                    break;
                }
            }
            if ($input->terminals->nameAt($input->index - 1) === 'ADD') {
                foreach ($input->terminals->terminals as $index => $terminal) {
                    if ($index < $input->index && $terminal->name === 'AUTH_FACTOR_NUMBER' && $terminal->ancestor('alter_user') === $owner) {
                        $values = ['3'];
                        break;
                    }
                }
            }
        }
        return (new ValueLexemeGenerator('AUTH_FACTOR_NUMBER', new WordDomain($values), $values, 'number', 'sql/sql_yacc.yy:factor'))->generate($input);
    }
}
