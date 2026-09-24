<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch;

use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Operand rules shared by text search configuration mappings.
 * @visibility SqlSemantics
 */
final class TextSearchInvariant
{
    /**
     * Token types form a nonempty list of identifiers.
     * @param list<string> $tokenTypes
     * @throws InvalidStructure
     */
    public static function tokenTypes(array $tokenTypes): void
    {
        Collections::strings(Collections::nonEmpty($tokenTypes));
        foreach ($tokenTypes as $tokenType) {
            TypeSystemInvariant::identifier($tokenType);
        }
    }
}
