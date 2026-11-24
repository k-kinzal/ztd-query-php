<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Layout;

/**
 * Resolves transient tokens into checked diagnostic presentation values.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ImplicitCheckLowering
{
    /**
     * Constructs the presentation for an admitted implicit comparison name.
     */
    public function label(?Comparison $condition, ?Layout $layout): ImplicitCheckLabel
    {
        $error = 'This column constraint has no complete representation without an explicit CHECK label or a checked implicit comparison.';
        Check::input($condition !== null && $condition->left instanceof ColumnReference
            && $condition->right instanceof IntegerLiteral && $layout !== null && count($layout->tokens) === 3, $error);
        [$column, $operator, $value] = $layout->tokens;
        $token = ComparisonSpelling::tryFrom($operator->text);
        Check::input($token !== null && preg_match('/\A[0-9]+\z/', $value->text) === 1, $error);
        $numeric = ltrim($value->text, '0');
        $decimal = $numeric === '' ? '0' : $numeric;
        Check::input($decimal === $condition->right->decimal, $error);

        return $this->presentation($column->text, $token, $operator->gap, $value->gap, strlen($value->text) - strlen($decimal));
    }

    /**
     * Decodes each admitted identifier delimiter into one checked name.
     */
    public function presentation(string $column, ComparisonSpelling $token, string $before, string $after, int $zeroes): ImplicitCheckLabel
    {
        $leader = substr($column, 0, 1);
        $inner = substr($column, 1, -1);
        $name = match ($leader) {
            '"' => str_replace('""', '"', $inner),
            '`' => str_replace('``', '`', $inner),
            '[' => $inner,
            default => $column,
        };

        return new ImplicitCheckLabel(new Identifier($name), $token, $name !== $column, $before, $after, $zeroes);
    }
}
