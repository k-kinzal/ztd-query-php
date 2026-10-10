<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Clock;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Storage\Store;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Scalar;

/**
 * Checks literal defaults of large and spatial columns before attempting value conversion.
 *
 * NULL follows the column's nullability. Without strict mode an empty string default is
 * replaced by the type's implicit value with a warning; spatial columns permit that exception only in MySQL 5.6.
 * Verified through CREATE TABLE, SHOW WARNINGS and INSERT on MySQL 5.6.51 and 8.4.7.
 *
 * @visibility MySqlMemory
 */
final class LiteralDefaults
{
    /**
     * Answers a substituted default, or null when ordinary value conversion should handle it.
     *
     * @throws SqlError When the column's type refuses a literal default
     */
    public function check(ColumnDefinition $column, Scalar $value, Context $context): ?Fill
    {
        if ($value instanceof NullLiteral || !in_array($column->domain->field, [Field::Blob, Field::Json, Field::Geometry], true)) {
            return null;
        }
        $legacy = $context->modes->release === GrammarRelease::MySql5651;
        $code = SchemaError::BlobCantHaveDefault;
        $text = $legacy ? "BLOB/TEXT column '" . $column->name . "' can't have a default value" : $code->message($column->name);
        if ($value instanceof StringLiteral && $value->value() === '' && !$context->modes->strict() && ($legacy || $column->domain->field !== Field::Geometry)) {
            $context->diagnostics->warning($code, $text);

            $default = $column->nullable() ? null : ($column->domain->field === Field::Json ? 'null' : '');

            return Fill::constant($default, $default);
        }

        throw new SqlError($code, $text);
    }

    /**
     * Evaluates a literal default under strict storage conversion, retaining clock defaults for each write.
     *
     * @throws SqlError When the default cannot be stored in the column
     */
    public function value(Evaluable $evaluable, ColumnDefinition $column, Context $context): Fill
    {
        if (($evaluable instanceof Retyped ? $evaluable->evaluable : $evaluable) instanceof Clock) {
            return new Fill(true, null, $evaluable, true, 'CURRENT_TIMESTAMP' . ($column->domain->decimals > 0 ? '(' . $column->domain->decimals . ')' : ''));
        }
        $frame = new Frame($context);
        $raw = $evaluable->evaluate($frame);
        if ($raw === null && !$column->nullable()) {
            throw SchemaError::InvalidDefault->error($column->name);
        }
        $strict = $context->strict;
        $context->strict = true;
        try {
            $stored = (new Store($context))->value($raw, $evaluable->domain(), $column);
        } catch (SqlError $error) {
            throw new SqlError(SchemaError::InvalidDefault, SchemaError::InvalidDefault->message($column->name), $error);
        } finally {
            $context->strict = $strict;
        }

        return Fill::constant($stored, $raw === null ? null : (string) $stored);
    }
}
