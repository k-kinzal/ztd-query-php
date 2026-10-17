<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Leaf\Clock;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Call\Clock as ClockKind;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

/**
 * Applies implicit TIMESTAMP defaults when explicit_defaults_for_timestamp is disabled.
 *
 * The first TIMESTAMP gains both automatic properties when it is nonnullable and neither is
 * written. Later nonnullable TIMESTAMP columns default to zero; an ON UPDATE clause
 * without a default does too. Zero defaults still obey NO_ZERO_DATE at definition time.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/timestamp-initialization.html.
 *
 * @visibility MySqlMemory
 */
final class TimestampDefaults
{
    /**
     * Completes the default and automatic-update properties of one column.
     *
     * @throws \MySqlMemory\Error\SqlError When an implicit zero default is invalid
     */
    public function apply(ColumnDefinition $column, ColumnElement $element, CreateTable $create, Context $context): ColumnDefinition
    {
        if ($column->domain->field !== Field::Timestamp || $column->nullable() || $element->specification instanceof GeneratedColumn || !in_array($context->variables->read('explicit_defaults_for_timestamp'), ['OFF', '0', 0], true)) {
            return $column;
        }
        foreach ($element->specification->columnAttributes() as $attribute) {
            if ($attribute instanceof DefaultLiteral || $attribute instanceof DefaultExpression) {
                return $column;
            }
        }
        if (!$column->onUpdateNow && $this->first($element, $create)) {
            $default = (new LiteralDefaults())->value(new Clock(ClockKind::Now, $column->domain), $column, $context);

            return $column->withDefault($default)->withOnUpdate(true);
        }
        $zero = new Constant(Domain::string(19, Collation::binary()), '0000-00-00 00:00:00');

        return $column->withDefault((new LiteralDefaults())->value($zero, $column, $context));
    }

    /**
     * Tells whether this is the table's first TIMESTAMP, including nullable columns.
     */
    public function first(ColumnElement $element, CreateTable $create): bool
    {
        foreach ($create->elements as $candidate) {
            if (!$candidate instanceof ColumnElement) {
                continue;
            }
            $type = $candidate->specification->dataType();
            if ($type instanceof Temporal && $type->kind === TemporalKind::Timestamp) {
                return $candidate === $element;
            }
        }

        return false;
    }
}
