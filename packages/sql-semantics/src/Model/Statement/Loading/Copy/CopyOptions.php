<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The options of COPY with their server defaults; null marks an option left to its format default.
 * Legacy keyword options such as CSV HEADER or DELIMITER AS ',' bind to the same options as the parenthesized list.
 * @visibility public
 * @example Reading legacy keyword options
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COPY t TO STDOUT WITH CSV HEADER DELIMITER AS ';'", strict: false);
 *     [$statement->options->format->value, $statement->options->header->value, $statement->options->delimiter] // => ['csv', 'true', ';']
 * @example Rejecting a delimiter in binary format
 *     new \SqlSemantics\Model\Statement\Loading\Copy\CopyOptions(format: \SqlSemantics\Model\Statement\Loading\Copy\CopyFormat::Binary, delimiter: ','); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CopyOptions
{
    /**
     * @param CopyFormat $format Data format
     * @param bool $freeze Whether loaded rows are frozen
     * @param string|null $delimiter Column separator; null uses a tab in text format and a comma in CSV
     * @param string|null $null Spelling of NULL; null uses \N in text format and an empty string in CSV
     * @param string|null $default Spelling of a column default when reading; null has none
     * @param CopyHeader $header Header line handling
     * @param string|null $quote CSV quote character; null uses a double quote
     * @param string|null $escape CSV escape character; null uses the quote character
     * @param ColumnChoice|null $forceQuote Columns always quoted when writing CSV
     * @param ColumnChoice|null $forceNotNull Columns whose NULL spelling is read as an empty string in CSV
     * @param ColumnChoice|null $forceNull Columns whose quoted NULL spelling is read as NULL in CSV
     * @param string|null $encoding Encoding name of the data; null uses the client encoding
     * @param CopyErrorAction|null $onError Conversion error handling when reading; null stops at the first error
     * @param CopyLogVerbosity $logVerbosity Amount of messages about skipped rows
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly CopyFormat $format = CopyFormat::Text,
        public readonly bool $freeze = false,
        public readonly ?string $delimiter = null,
        public readonly ?string $null = null,
        public readonly ?string $default = null,
        public readonly CopyHeader $header = CopyHeader::Absent,
        public readonly ?string $quote = null,
        public readonly ?string $escape = null,
        public readonly ?ColumnChoice $forceQuote = null,
        public readonly ?ColumnChoice $forceNotNull = null,
        public readonly ?ColumnChoice $forceNull = null,
        public readonly ?string $encoding = null,
        public readonly ?CopyErrorAction $onError = null,
        public readonly CopyLogVerbosity $logVerbosity = CopyLogVerbosity::Default,
    ) {
        CopyOptionRules::format($this);
        CopyOptionRules::characters($this);
    }

    /**
     * Returns the column separator in effect.
     */
    public function effectiveDelimiter(): string
    {
        return $this->delimiter ?? ($this->format === CopyFormat::Csv ? ',' : "\t");
    }

    /**
     * Returns the NULL spelling in effect.
     */
    public function effectiveNull(): string
    {
        return $this->null ?? ($this->format === CopyFormat::Csv ? '' : '\\N');
    }
}
