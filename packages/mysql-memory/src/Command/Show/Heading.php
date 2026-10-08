<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Command\Output;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * One column of the rows a SHOW, DESCRIBE, EXPLAIN or HELP statement answers, with the metadata the server sends for it.
 *
 * A text column is held in the server's system character set, utf8mb3, or in the collation of
 * the table it is read from, and sent in the character set of the results like any string: its length counts the bytes of its characters
 * in that set. Every other column is sent in the binary character set with its display length.
 * The type, flags, decimals and the names of the column, its table and its database are those
 * the server reports for the statement (verified on a live 8.4 server).
 *
 * @visibility MySqlMemory
 */
final class Heading
{
    /**
     * @param string $name The column name
     * @param Field $field The type code
     * @param int $length The length in characters of a text column, else the display length
     * @param int $flags The ColumnFlag bits
     * @param int $decimals The decimals the server reports
     * @param bool $text Whether the column is text in the system character set
     * @param string $originalName The name of the column the server reads it from
     * @param string $table The name of the table the server reads it through
     * @param string $originalTable The name of the base table
     * @param string $schema The database of the base table
     * @param string $collation The collation a text column is held in
     */
    public function __construct(
        public readonly string $name,
        public readonly Field $field,
        public readonly int $length,
        public readonly int $flags = 0,
        public readonly int $decimals = 0,
        public readonly bool $text = false,
        public readonly string $originalName = '',
        public readonly string $table = '',
        public readonly string $originalTable = '',
        public readonly string $schema = '',
        public readonly string $collation = 'utf8mb3_general_ci',
    ) {
    }

    /**
     * Creates a text column of a number of characters.
     */
    public static function text(string $name, Field $field, int $length, int $flags = 0, int $decimals = 0, string $originalName = '', string $table = '', string $originalTable = '', string $schema = '', string $collation = 'utf8mb3_general_ci'): self
    {
        return new self($name, $field, $length, $flags, $decimals, true, $originalName, $table, $originalTable, $schema, $collation);
    }

    /**
     * Answers the column definition the server sends, in the character set of the results.
     *
     * A session without a character set of the results sends the text in the character set it is held in.
     */
    public function column(?Charset $results): ResultColumn
    {
        if (!$this->text) {
            return new ResultColumn($this->name, $this->field, $this->length, $this->decimals, $this->flags, 63, $this->originalName, $this->table, $this->originalTable, $this->schema);
        }
        $blob = in_array($this->field, [Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob], true);
        $held = Collation::known($this->collation);
        $charset = $results === null ? $held->id : $results->defaultCollation(GrammarRelease::MySql847)->id;
        $length = (new Output())->converted($this->length, ($results ?? $held->charset)->maxLength, $blob);

        return new ResultColumn($this->name, $this->field, $length, $this->decimals, $this->flags, $charset, $this->originalName, $this->table, $this->originalTable, $this->schema);
    }

    /**
     * Answers the domain a WHERE condition reads the column in.
     */
    public function domain(): Domain
    {
        $nullable = ($this->flags & ColumnFlag::NotNull->value) === 0;
        $unsigned = ($this->flags & ColumnFlag::Unsigned->value) !== 0;
        if ($this->text) {
            return Domain::string($this->length, Collation::known($this->collation), $this->field)->withNullable($nullable);
        }

        return match ($this->field) {
            Field::Tiny, Field::Short, Field::Int24, Field::Long, Field::LongLong => Domain::integer($this->field, $this->length, $unsigned)->withNullable($nullable),
            Field::Decimal, Field::NewDecimal => Domain::decimal(max(1, $this->length - 2), $this->decimals, $unsigned)->withNullable($nullable),
            Field::Float, Field::Double => Domain::double($this->length, $this->decimals)->withNullable($nullable),
            Field::Timestamp, Field::DateTime => new Domain(Kind::DateTime, $this->field, 19, 0, false, Collation::binary(), $nullable),
            Field::Null => Domain::null(),
            Field::Date, Field::Time, Field::Year, Field::NewDate, Field::VarChar, Field::Bit, Field::Vector, Field::Json, Field::Enum, Field::Set, Field::TinyBlob,
            Field::MediumBlob, Field::LongBlob, Field::Blob, Field::VarString, Field::String, Field::Geometry => Domain::string($this->length, Collation::binary(), $this->field)->withNullable($nullable),
        };
    }
}
