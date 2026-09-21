<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Schema\TypeShape;
use SqlFixture\Syntax\NodeReader;
use SqlParser\Parser\Node;

/**
 * Reads the type name, dimensions and array marker from a Typename node.
 *
 * @visibility root
 */
final class TypeDeclaration
{
    /**
     * Recognizes the dialect numeric types that accept precision and scale.
     */
    public function isDecimalType(string $type): bool
    {
        return in_array($type, ['DECIMAL', 'NUMERIC', 'DEC'], true);
    }

    /**
     * Returns the type name as the grammar spells it, without a schema qualifier or modifiers.
     */
    public function typeName(Node $typename): string
    {
        $reader = new NodeReader();
        $simple = $reader->child($typename, 'SimpleTypename');
        $words = [];
        foreach ($simple === null ? [] : $reader->wordsOutsideParentheses($simple) as $word) {
            if ($word === '.') {
                $words = [];
                continue;
            }
            $words[] = $word;
        }

        return strtoupper(implode(' ', $words));
    }

    /**
     * Interprets the declared type, folding SERIAL types to their integer type with auto increment.
     */
    public function parse(Node $typename): TypeShape
    {
        $reader = new NodeReader();
        $name = $this->typeName($typename);
        $simple = $reader->child($typename, 'SimpleTypename');
        $numbers = [];
        foreach ($simple === null ? [] : $simple->tokens() as $token) {
            if ($token->is('ICONST')) {
                $numbers[] = (int) $token->text;
            }
        }
        $bounds = $reader->child($typename, 'opt_array_bounds');
        $suffix = ($bounds !== null && !$bounds->isEmpty()) || $reader->containsToken($typename, 'ARRAY') ? '_ARRAY' : '';

        $serial = match ($name) {
            'SERIAL', 'SERIAL4' => 'INTEGER',
            'BIGSERIAL', 'SERIAL8' => 'BIGINT',
            'SMALLSERIAL', 'SERIAL2' => 'SMALLINT',
            default => null,
        };
        if ($serial !== null) {
            return new TypeShape($serial . $suffix, autoIncrement: true);
        }
        if ($numbers === []) {
            return new TypeShape($name . $suffix);
        }
        if ($this->isDecimalType($name)) {
            return new TypeShape($name . $suffix, null, $numbers[0], $numbers[1] ?? 0);
        }

        return new TypeShape($name . $suffix, $numbers[0]);
    }
}
