<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * The ON UPDATE clause of a TIMESTAMP or DATETIME column: the current timestamp function that updates the column when another column of the row changes.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/timestamp-initialization.html.
 *
 * @visibility public
 * @example Reading the clause of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');
 *     $create->elements[0]->specification->attributes[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\OnUpdate // => true
 */
final class OnUpdate implements ColumnAttribute
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ClockCall|Value\CurrentTimestamp $value The value
     */
    public function __construct(public readonly ClockCall|Value\CurrentTimestamp $value, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input(!$value instanceof ClockCall || $value->clock === Clock::Now, 'A column timestamp clause accepts only CURRENT_TIMESTAMP and its synonyms.');
    }

    /**
     * Derives the value inside the table definition.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
        if (!$this->value instanceof Value\CurrentTimestamp) {
            $derivation->scalar($this->value, $scope);
        }
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON', 'UPDATE')->node($this->value);
    }
}
