<?php

declare(strict_types=1);

namespace Fuzz\Target;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use UnitEnum;

/**
 * Compares semantic data and complete terminal structure independently of source layout.
 */
final class SemanticFacts
{
    /**
     * @return mixed
     */
    public static function read(mixed $value): mixed
    {
        if ($value instanceof UnitEnum) {
            return $value;
        }
        if ($value instanceof Node) {
            return array_map(static fn (Token $token): array => [$token->name, $token->text], $value->tokens());
        }
        if ($value instanceof Token) {
            return [$value->name, $value->text];
        }
        if (is_array($value)) {
            return array_map(self::read(...), $value);
        }
        if (is_object($value)) {
            $properties = get_object_vars($value);
            unset($properties['source'], $properties['sql']);
            return [$value::class, array_map(self::read(...), $properties)];
        }
        return $value;
    }
}
