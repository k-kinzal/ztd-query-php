<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use Override;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;

/**
 * sql_yacc.yy literal actions impose charset validity on introduced string, hex and bit values.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy#L14780-L14807
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 */
final class CharsetValueLexemeGenerator implements LexemeGenerator
{
    /**
     * Composes separate constructive domains while preserving each child's explicit-spelling contract.
     */
    public function __construct(private readonly LexemeGenerator $ordinary, private readonly LexemeGenerator $introduced)
    {
    }

    /**
     * Uses a domain valid under every declared introducer, including a charset fixed by a partial caller plan.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        $introduced = ($input->terminals->terminals[$input->index - 1]->name ?? null) === 'UNDERSCORE_CHARSET';
        return ($introduced ? $this->introduced : $this->ordinary)->generate($input);
    }
}
