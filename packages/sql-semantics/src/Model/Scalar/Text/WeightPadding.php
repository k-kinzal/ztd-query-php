<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Text;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The `AS CHAR(n)` or `AS BINARY(n)` of a MySQL WEIGHT_STRING: the input is taken as a nonbinary string padded with
 * spaces to n characters, or as a binary string padded with 0x00 bytes to n bytes.
 * @visibility public
 * @example Reading the padding of a weight string
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SELECT WEIGHT_STRING('ab' AS BINARY(4))");
 *     [$query->outputs[0]->expression->padding->binary, $query->outputs[0]->expression->padding->length] // => [true, 4]
 */
final class WeightPadding
{
    /**
     * @param bool $binary AS BINARY pads bytes; AS CHAR pads characters
     * @throws InvalidStructure
     */
    public function __construct(public readonly bool $binary, public readonly int $length)
    {
        if ($length < 1) {
            throw new InvalidStructure('A WEIGHT_STRING padding length is positive.');
        }
    }
}
