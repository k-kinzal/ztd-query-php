<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Schema\TypeShape;
use SqlFixture\Syntax\NodeReader;
use SqlParser\Parser\Node;

/**
 * Reads the declared type name and its dimensions from a typetoken node.
 *
 * @visibility root
 */
final class TypeDeclaration
{
    /**
     * Returns the declared type words, or BLOB when the column declares no type.
     */
    public function typeName(Node $typetoken): string
    {
        $reader = new NodeReader();
        $typename = $reader->child($typetoken, 'typename');
        $words = $typename === null ? [] : array_map('strtoupper', $reader->wordsOutsideParentheses($typename));
        if (count($words) >= 2 && $words[count($words) - 2] === 'GENERATED' && $words[count($words) - 1] === 'ALWAYS') {
            array_splice($words, -2);
        }

        return $words === [] ? 'BLOB' : implode(' ', $words);
    }

    /**
     * Interprets the declared type parameters before column constraints are applied.
     */
    public function parse(Node $typetoken): TypeShape
    {
        $name = $this->typeName($typetoken);
        $numbers = [];
        foreach ($typetoken->find('signed') as $signed) {
            foreach ($signed->tokens() as $token) {
                if ($token->is('INTEGER') || $token->is('FLOAT')) {
                    $numbers[] = (int) $token->text;
                }
            }
        }
        return TypeShape::fromNumbers($name, $numbers);
    }
}
