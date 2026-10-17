<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Syntax\NodeReader;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * The column attributes declared after a type, read from the syntax tree.
 *
 * @visibility root
 */
final class ColumnAttributes
{
    /**
     * Records the attributes a column declares, defaulting to a plain nullable column.
     */
    public function __construct(
        public readonly bool $nullable = true,
        public readonly bool $autoIncrement = false,
        public readonly bool $primaryKey = false,
        public readonly ?Node $default = null,
    ) {
    }

    /**
     * Reads every column_attribute under a field definition.
     */
    public function read(Node $fieldDef): self
    {
        $reader = new NodeReader();
        $nullable = true;
        $autoIncrement = false;
        $primaryKey = false;
        $default = null;
        foreach ($fieldDef->find('column_attribute') as $attribute) {
            $first = $attribute->children[0] ?? null;
            if ($first instanceof Node) {
                if ($first->name === 'not' && $reader->token($attribute, 'NULL_SYM') !== null) {
                    $nullable = false;
                } elseif ($first->name === 'opt_primary') {
                    $primaryKey = true;
                }
                continue;
            }
            if (!$first instanceof Token) {
                continue;
            }
            if ($first->is('NULL_SYM')) {
                $nullable = true;
            } elseif ($first->is('DEFAULT_SYM')) {
                $default = $attribute;
            } elseif ($first->is('AUTO_INC')) {
                $autoIncrement = true;
            } elseif ($first->is('SERIAL_SYM')) {
                $autoIncrement = true;
                $nullable = false;
            }
        }

        return new self($nullable, $autoIncrement, $primaryKey, $default);
    }
}
