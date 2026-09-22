<?php

declare(strict_types=1);

namespace Fuzz\Target;

use RuntimeException;
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
     * @throws RuntimeException
     */
    public static function read(mixed $value): mixed
    {
        if ($value instanceof UnitEnum) {
            return $value;
        }
        if ($value instanceof Node || $value instanceof Token || $value instanceof \SqlSemantics\Model\Sql\Tree) {
            throw new RuntimeException('Semantic operands must be classified; parser syntax and generic SQL trees are not semantic payloads.');
        }
        if ($value instanceof \SqlSemantics\Model\Diagnostic) {
            return [$value::class, $value->reason];
        }
        if (is_array($value)) {
            return array_map(self::read(...), $value);
        }
        if (is_object($value)) {
            $properties = get_object_vars($value);
            unset($properties['source'], $properties['origin']);
            return [$value::class, array_map(self::read(...), $properties)];
        }
        return $value;
    }
}
