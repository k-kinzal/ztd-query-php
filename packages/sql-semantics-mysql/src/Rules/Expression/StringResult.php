<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Derives the result type of string concatenation.
 *
 * Rule: MYSQL-STRING-RESULT-001. The result is a binary string (VARBINARY)
 * when an operand is a binary string and a character string (VARCHAR)
 * otherwise; numbers and temporal values are converted to character
 * strings. When an operand may or may not be a binary string the result is
 * a choice of both. A missing or invalid operand type decides the result
 * (MYSQL-TYPE-ALTERNATIVES-001). Terminates: no recursion.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_concat.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StringResult
{
    /**
     * Answers the type of the concatenation of operands of the given types.
     *
     * @param list<TypeFact> $operands
     */
    public function concatenation(array $operands): TypeFact
    {
        $alternatives = new Alternatives();
        $blocking = $alternatives->blocking($operands);
        if ($blocking !== null) {
            return $blocking;
        }
        $certain = false;
        $possible = false;
        foreach ($operands as $operand) {
            $binary = 0;
            $types = $alternatives->of($operand);
            foreach ($types as $type) {
                $binary += $type instanceof Binary ? 1 : 0;
            }
            $certain = $certain || $binary === count($types);
            $possible = $possible || $binary > 0;
        }
        if ($certain) {
            return $alternatives->known([new Binary(BinaryKind::VarBinary)]);
        }

        return $alternatives->known($possible ? [new Character(CharacterKind::VarChar), new Binary(BinaryKind::VarBinary)] : [new Character(CharacterKind::VarChar)]);
    }
}
