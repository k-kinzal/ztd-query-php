<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Type\Identity\ArrayDimension;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

/**
 * Reads declared array bounds independently from the element type.
 * @visibility SqlSemantics
 */
final class ArrayBounds
{
    /**
     * @return non-empty-list<ArrayDimension>
     */
    public static function read(Node $source, ?Node $bounds): array
    {
        $dimensions = [];
        $size = '';
        $inside = false;
        $tokens = $bounds !== null ? $bounds->tokens() : array_merge(...array_map(static fn ($child): array => $child instanceof \SqlParser\Lexer\Token ? [$child] : ($child->name === 'Iconst' ? $child->tokens() : []), $source->children));
        foreach ($tokens as $token) {
            if ($token->text === '[') {
                $inside = true;
                $size = '';
            } elseif ($token->text === ']') {
                $dimensions[] = new ArrayDimension($size === '' ? null : new NumericParameter($size));
                $inside = false;
            } elseif ($inside) {
                $size .= $token->text;
            }
        }
        return $dimensions === [] ? [new ArrayDimension()] : $dimensions;
    }
}
