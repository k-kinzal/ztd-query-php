<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use SqlFaker\Generation\Candidate\IntegerLexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;

/**
 * Create_field::init requires M >= D; my_decimal_trim gives (0,0) its default precision.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/create_field.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/field.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/field.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/field.cc
 */
final class PrecisionLexemeGenerator implements LexemeGenerator
{
    /**
     * Uses the already resolved scale from the same precision occurrence to construct the width domain.
     */
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        $name = $input->terminal()->name;
        if (!in_array($name, ['DECIMAL_DIGITS_NUMBER', 'FLOAT_DIGITS_NUMBER'], true)) {
            return null;
        }
        $scope = $input->terminal()->ancestor('precision');
        $scale = null;
        foreach ($input->right->parts as $part) {
            if ($part->lexeme->origin->name === 'NUMERIC_SCALE_NUMBER' && $part->lexeme->origin->ancestor('precision') === $scope) {
                $scale = (int) $part->lexeme->text;
                break;
            }
        }
        if ($scale === null) {
            return LexemeCandidates::of();
        }
        $maximum = $name === 'DECIMAL_DIGITS_NUMBER' ? '65' : '255';
        $domain = new IntegerLexemeGenerator($name, (string) $scale, $maximum, [(string) $scale, $maximum], 'sql/create_field.cc:precision-and-scale');
        return $domain->generate($input);
    }
}
