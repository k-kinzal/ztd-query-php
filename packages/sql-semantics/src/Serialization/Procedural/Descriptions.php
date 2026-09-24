<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Procedural;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Inspection\Schema\DescribeTableStatement;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes the table form of DESCRIBE with its optional column pattern.
 * @visibility SqlSemantics
 */
final class Descriptions
{
    /**
     * Spells the pattern as a string, or as a hexadecimal literal when its bytes are not printable text.
     */
    public static function write(DescribeTableStatement $statement): Tree
    {
        $pattern = $statement->pattern === null ? [] : [self::bytes($statement->pattern)];
        return new Tree('describe', [Build::keyword('DESCRIBE'), Relations::target($statement->table, Dialect::MySql), ...$pattern]);
    }

    /**
     * Writes bytes as a string literal when they are printable UTF-8 text, otherwise as a hexadecimal literal.
     */
    public static function bytes(string $value): Tree
    {
        if (mb_check_encoding($value, 'UTF-8') && preg_match('/[\x00-\x1f\x7f]/', $value) !== 1) {
            return Requests::text($value);
        }
        return new Tree('literal', [new Atom('literal', "X'" . bin2hex($value) . "'")]);
    }
}
