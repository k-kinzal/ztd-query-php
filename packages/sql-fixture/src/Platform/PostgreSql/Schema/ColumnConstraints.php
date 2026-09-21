<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Syntax\NodeReader;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * The column constraints declared after a type, read from the syntax tree.
 *
 * @visibility root
 */
final class ColumnConstraints
{
    /**
     * Records the constraints a column declares, defaulting to a plain nullable column.
     */
    public function __construct(
        public readonly bool $nullable = true,
        public readonly bool $primaryKey = false,
        public readonly bool $identity = false,
        public readonly bool $generated = false,
        public readonly ?Node $default = null,
    ) {
    }

    /**
     * Reads every ColConstraintElem under a column definition.
     */
    public function read(Node $columnDef): self
    {
        $reader = new NodeReader();
        $nullable = true;
        $primaryKey = false;
        $identity = false;
        $generated = false;
        $default = null;
        foreach ($columnDef->find('ColConstraintElem') as $constraint) {
            $first = $constraint->children[0] ?? null;
            if (!$first instanceof Token) {
                continue;
            }
            if ($first->is('NOT')) {
                $nullable = false;
            } elseif ($first->is('NULL_P')) {
                $nullable = true;
            } elseif ($first->is('DEFAULT')) {
                $default = $reader->child($constraint, 'b_expr');
            } elseif ($first->is('PRIMARY')) {
                $primaryKey = true;
            } elseif ($first->is('GENERATED') && $reader->containsToken($constraint, 'IDENTITY_P')) {
                $identity = true;
            } elseif ($first->is('GENERATED')) {
                $generated = true;
            }
        }

        return new self($nullable, $primaryKey, $identity, $generated, $default);
    }
}
