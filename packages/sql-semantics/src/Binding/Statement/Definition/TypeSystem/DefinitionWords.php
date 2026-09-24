<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Reads the uppercased token spellings of an optional clause.
 * @visibility SqlSemantics
 */
final class DefinitionWords
{
    /**
     * An absent clause has no words.
     * @return list<string>
     */
    public static function of(?Node $source): array
    {
        return $source === null ? [] : array_map(static fn (Token $token): string => strtoupper($token->text), $source->tokens());
    }
}
