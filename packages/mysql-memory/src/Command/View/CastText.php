<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

/**
 * Writes character casts with their resolved stored character set.
 * @visibility MySqlMemory
 */
final class CastText
{
    /**
     * @param ExpressionText $expressions The writer for the original operand
     */
    public function __construct(public readonly ExpressionText $expressions)
    {
    }

    /**
     * Writes the native CHAR representation without consulting session input SQL.
     */
    public function write(Cast $cast): ?string
    {
        if (!in_array($cast->target->kind, [CastKind::Char, CastKind::NationalChar], true) || $cast->array) {
            return null;
        }
        $operand = $this->expressions->scalar($cast->operand);
        $domain = (new Precision())->domain($cast->scalarInformation()->type);
        if ($operand === null || $domain === null) {
            return null;
        }
        $length = $cast->target->length === null ? '' : '(' . (string) (int) $cast->target->length . ')';
        return 'cast(' . $operand . ' as char' . $length . ' charset ' . $domain->collation->charset->name . ')';
    }
}
