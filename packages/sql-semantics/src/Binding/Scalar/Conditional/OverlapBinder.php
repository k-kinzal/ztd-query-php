<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Conditional;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Temporal\PeriodOverlap;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds the PostgreSQL period predicates OVERLAPS and diagnoses the unimplemented UNIQUE predicate.
 * @visibility SqlSemantics
 */
final class OverlapBinder
{
    /**
     * Recognizes row OVERLAPS row, each row holding exactly a start and an end.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope): ?PeriodOverlap
    {
        if ($scope->identifiers->dialect !== Dialect::PostgreSql) {
            return null;
        }
        $first = $source->children[0] ?? null;
        if ($first instanceof Token && strtoupper($first->text) === 'UNIQUE' && Tree::child($source, ['select_with_parens']) !== null) {
            throw new InvalidSql(InputViolation::UniquePredicate, $source);
        }
        $rows = array_values(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Node && $child->name === 'row'));
        if (count($rows) !== 2) {
            return null;
        }
        $bounds = [];
        foreach ($rows as $row) {
            $items = (new ExpressionBinder())->bind($row, $scope)->inputs();
            if (count($items) !== 2) {
                throw new InvalidSql(InputViolation::OverlapsWidth, $row);
            }
            array_push($bounds, ...$items);
        }
        return new PeriodOverlap($source, $bounds[0], $bounds[1], $bounds[2], $bounds[3]);
    }
}
