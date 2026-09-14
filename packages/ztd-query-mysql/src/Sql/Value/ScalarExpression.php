<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Sql\Value;

use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Scalar Expression.
 *
 */
final class ScalarExpression
{
    /**
     * Render Expression for the supplied MySQL input.
     */
    public function renderExpression(mixed $value, ColumnDeclaration $type, bool $typed): string
    {
        if ($type->family === ColumnTypeFamily::BINARY) {
            return "X'" . bin2hex((new StringCoercion())->stringValue($value)) . "'";
        }

        if (is_int($value) && !$typed) {
            return (string) $value;
        }

        $string = is_float($value) ? var_export($value, true) : (new StringCoercion())->stringValue($value);
        if (!str_contains($string, '\\')) {
            return ("'" . str_replace("'", "''", $string) . "'");
        }
        $hex = bin2hex($string);

        return "CONVERT(X'$hex' USING utf8mb4)";
    }

    /**
     * Infer Type for the supplied MySQL input.
     */
    public function inferType(mixed $value): ColumnDeclaration
    {
        if (is_int($value)) {
            return new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INT');
        }

        return new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR');
    }
}
