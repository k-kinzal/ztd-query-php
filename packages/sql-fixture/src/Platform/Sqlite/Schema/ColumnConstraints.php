<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

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
        public readonly bool $autoIncrement = false,
        public readonly bool $generated = false,
        public readonly ?Node $default = null,
    ) {
    }

    /**
     * Reads every ccons under a column's constraint list.
     */
    public function read(Node $carglist): self
    {
        $reader = new NodeReader();
        $nullable = true;
        $primaryKey = false;
        $autoIncrement = false;
        $generated = false;
        $default = null;
        foreach ($carglist->find('ccons') as $constraint) {
            $first = $constraint->children[0] ?? null;
            if (!$first instanceof Token) {
                continue;
            }
            if ($first->is('NOT') && $reader->token($constraint, 'NULL') !== null) {
                $nullable = false;
            } elseif ($first->is('NULL')) {
                $nullable = true;
            } elseif ($first->is('DEFAULT')) {
                $default = $constraint;
            } elseif ($first->is('PRIMARY')) {
                $primaryKey = true;
                $autoIncrement = $reader->containsToken($constraint, 'AUTOINCR');
            } elseif ($first->is('GENERATED') || $first->is('AS')) {
                $generated = true;
            }
        }

        return new self($nullable, $primaryKey, $autoIncrement, $generated, $default);
    }
}
