<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Schema\TypeShape;
use SqlFixture\Syntax\NodeReader;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Reads the type name, length, precision and scale from a type node.
 *
 * @visibility root
 */
final class TypeParameters
{
    /**
     * Recognizes the dialect numeric types that accept precision and scale.
     */
    public function isDecimalType(string $type): bool
    {
        return in_array($type, ['DECIMAL', 'NUMERIC', 'DEC', 'FIXED'], true);
    }

    /**
     * Returns the declared type name with the grammar's synonyms folded to one spelling.
     */
    public function typeName(Node $type): string
    {
        $reader = new NodeReader();
        $first = $type->children[0] ?? null;
        $second = $type->children[1] ?? null;
        $words = $first instanceof Node ? $reader->wordsOutsideParentheses($first) : ($first instanceof Token ? [$first->text] : []);
        if ($first instanceof Token && $first->is('LONG_SYM') && $second instanceof Node && $second->name === 'varchar') {
            array_push($words, ...$reader->wordsOutsideParentheses($second));
        } elseif ($first instanceof Token && $first->is('LONG_SYM') && $second instanceof Token && $second->is('VARBINARY_SYM')) {
            $words[] = $second->text;
        }
        $name = strtoupper(implode(' ', $words));

        return match ($name) {
            'DOUBLE PRECISION' => 'DOUBLE',
            'CHARACTER', 'NATIONAL CHAR', 'NATIONAL CHARACTER', 'NCHAR' => 'CHAR',
            'CHAR VARYING', 'CHARACTER VARYING', 'NATIONAL VARCHAR', 'NVARCHAR', 'NCHAR VARCHAR', 'NATIONAL CHAR VARYING', 'NATIONAL CHARACTER VARYING', 'NCHAR VARYING' => 'VARCHAR',
            'LONG', 'LONG VARCHAR' => 'MEDIUMTEXT',
            'LONG VARBINARY' => 'MEDIUMBLOB',
            'SERIAL' => 'BIGINT',
            default => $name,
        };
    }

    /**
     * Returns the values an ENUM or SET type enumerates.
     *
     * @return list<string>
     */
    public function extractEnumValues(Node $type): array
    {
        $values = [];
        foreach ($type->find('text_string') as $item) {
            $token = (new NodeReader())->firstToken($item);
            if ($token === null) {
                continue;
            }
            $values[] = $token->is('TEXT_STRING') ? (new StringLiteral())->decode($token) : $token->text;
        }

        return $values;
    }

    /**
     * Interprets the declared type parameters before column constraints are applied.
     */
    public function parse(Node $type): TypeShape
    {
        $words = (new NodeReader())->wordsOutsideParentheses($type);
        $name = $this->typeName($type);
        $autoIncrement = strtoupper($words[0] ?? '') === 'SERIAL';
        $numbers = [];
        foreach ($type->tokens() as $token) {
            if ($token->is('NUM')) {
                $numbers[] = (int) $token->text;
            }
        }
        if ($numbers === []) {
            return new TypeShape($name, autoIncrement: $autoIncrement);
        }
        if ($this->isDecimalType($name)) {
            return new TypeShape($name, null, $numbers[0], $numbers[1] ?? 0, $autoIncrement);
        }

        return new TypeShape($name, $numbers[0], autoIncrement: $autoIncrement);
    }
}
