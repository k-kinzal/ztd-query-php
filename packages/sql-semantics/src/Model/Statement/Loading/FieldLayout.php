<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The FIELDS clause of a load: field terminator, enclosing quote and escape character; null keeps the server default.
 * @visibility public
 * @example Reading the field separators
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $fields = (new \SqlSemantics\Binder($schema))->bind("LOAD DATA INFILE 'rows.csv' INTO TABLE t FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'")->layout->fields;
 *     [$fields->terminator?->text, $fields->enclosure?->text, $fields->optionallyEnclosed, $fields->escape] // => ["','", "'\"'", true, null]
 */
final class FieldLayout
{
    /**
     * @param bool $optionallyEnclosed Whether only string fields are enclosed; requires an enclosure
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?Literal $terminator = null, public readonly ?Literal $enclosure = null, public readonly bool $optionallyEnclosed = false, public readonly ?Literal $escape = null)
    {
        if ($terminator !== null) {
            SeparatorText::bytes($terminator);
        }
        foreach ([$enclosure, $escape] as $character) {
            if ($character !== null && strlen(SeparatorText::bytes($character)) > 1) {
                throw new InvalidStructure('A field enclosure and escape character are at most one byte long.');
            }
        }
        if ($optionallyEnclosed && $enclosure === null) {
            throw new InvalidStructure('OPTIONALLY requires an enclosing character.');
        }
    }

    /**
     * Whether the clause leaves every separator at its default.
     */
    public function empty(): bool
    {
        return $this->terminator === null && $this->enclosure === null && $this->escape === null;
    }
}
