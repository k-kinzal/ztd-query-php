<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Table;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Converts every character column and the table default to a character set, optionally with a collation.
 * @visibility public
 * @example Converting to a named character set
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE utf8mb4_bin');
 *     [$statement->alterations[0]->characterSet, $statement->alterations[0]->collation] // => ['utf8mb4', 'utf8mb4_bin']
 */
final class ConvertCharacterSet implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string|InheritedCharacterSet $characterSet, public readonly ?string $collation = null)
    {
        if (is_string($characterSet)) {
            AlterationInvariant::name($characterSet);
        }
        if ($collation !== null) {
            AlterationInvariant::name($collation);
        }
    }
}
