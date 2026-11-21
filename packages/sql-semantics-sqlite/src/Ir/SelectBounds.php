<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks scope membership and expanded expression limits at the SELECT boundary.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SelectBounds
{
    /**
     * Checks every expression occurrence, including repeated uses of a shared value.
     *
     * @param non-empty-list<ResultColumn> $results The result expressions
     * @param TableSource|null $source The source whose slots may be used
     */
    public function check(array $results, ?TableSource $source): void
    {
        Check::input(count($results) <= 2000, 'A SELECT can have at most 2000 result columns in this profile.');
        $nodes = 0;
        $parameterBytes = 0;
        foreach ($results as $result) {
            $pending = [[$result->expression, 0]];
            while ($pending !== []) {
                [$expression, $height] = array_pop($pending);
                Check::input($height <= 32 && ++$nodes <= 8192, 'Expression height or expanded node count exceeds the IR profile.');
                match (true) {
                    $expression instanceof ColumnReference => Check::input(
                        isset($source->columns[$expression->slot]),
                        'Column slot must belong to the SELECT source.',
                    ),
                    $expression instanceof Parameter => $parameterBytes += strlen($expression->label),
                    $expression instanceof Arithmetic => array_push($pending, [$expression->right, $height + 1], [$expression->left, $height + 1]),
                    $expression instanceof IntegerLiteral, $expression instanceof NullLiteral => null,
                };
                Check::input($parameterBytes <= 990000000, 'Numbered parameter labels exceed the SQLite statement size budget.');
            }
        }
    }
}
