<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\LoadData;

use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Validated delimiters, quoting and line prefix from a LOAD DATA statement.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class InputFormat
{
    /**
     * Construct a record format from validated single-byte quotes and nonempty delimiters.
     */
    public function __construct(
        /**
         * fieldTerminator configured by LOAD DATA.
         */
        public readonly string $fieldTerminator,
        /**
         * enclosure configured by LOAD DATA.
         */
        public readonly string $enclosure,
        /**
         * escape configured by LOAD DATA.
         */
        public readonly string $escape,
        /**
         * linePrefix configured by LOAD DATA.
         */
        public readonly string $linePrefix,
        /**
         * lineTerminator configured by LOAD DATA.
         */
        public readonly string $lineTerminator,
    ) {
    }

    /**
     * @throws UnsupportedSqlException
     */
    public static function fromStatement(LoadStatement $statement, string $sql): self
    {
        $fieldTerminator = (new ColumnMapping())->optionValue($statement->fields_options, 'TERMINATED BY', "\t");
        $enclosure = (new ColumnMapping())->optionValue($statement->fields_options, 'ENCLOSED BY', '');
        $escape = (new ColumnMapping())->optionValue($statement->fields_options, 'ESCAPED BY', '\\');
        $linePrefix = (new ColumnMapping())->optionValue($statement->lines_options, 'STARTING BY', '');
        $lineTerminator = (new ColumnMapping())->optionValue($statement->lines_options, 'TERMINATED BY', "\n");
        if ($fieldTerminator === '' || $lineTerminator === '') {
            throw new UnsupportedSqlException($sql, 'LOAD DATA fixed-row input is not supported');
        }
        if (strlen($enclosure) > 1 || strlen($escape) > 1) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA enclosure and escape must be single-byte values');
        }

        return new self($fieldTerminator, $enclosure, $escape, $linePrefix, $lineTerminator);
    }
}
