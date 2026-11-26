<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertSelect as ParsedQueryInsert;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;

/**
 * Resolves the target and closes literal VALUES at the write boundary.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class InsertLowering
{
    /**
     * Publishes the checked ordered write of literal rows.
     */
    public function lower(BoundStatement $operation, InsertRows $statement): Insert
    {
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'The INSERT requires the native language profile.');
        Check::input(
            $statement->with === null && $statement->overriding === null && $statement->conflict === null,
            'A complete INSERT requires the ordinary VALUES operation.'
        );
        [$target, $returning] = (new InsertTargetBinding())->lower($operation, $statement);
        $searchPath = $operation->context->searchPath;
        $catalogFirst = ($searchPath[0]->value === 'pg_temp' ? ($searchPath[1]->value ?? null) : $searchPath[0]->value) === 'pg_catalog';
        $expression = new ExpressionLowering();
        $empty = new SourceBinding($operation, null);
        $rows = [];
        foreach ($statement->rows->rows() as $row) {
            Check::input(count($row->values) === count($target->written), 'Every inserted row must match the target width.');
            $values = [];
            foreach ($row->values as $index => $value) {
                $item = $expression->result($value, $empty, $catalogFirst, $target->written[$index]->kind);
                Check::input(
                    $item instanceof IntegerLiteral || $item instanceof WideIntegerLiteral || $item instanceof TextLiteral
                        || $item instanceof BooleanLiteral || $item instanceof NullLiteral,
                    'A supplied INSERT value requires an explicit native literal or NULL.',
                );
                $values[] = new InsertCell($item instanceof NullLiteral ? null : $item);
            }
            $rows[] = new InsertRow($values);
        }

        return new Insert($target, $rows, $profile->grammar, $returning);
    }

    /**
     * Publishes the query write after resolving its independent input and writable targets.
     */
    public function lowerQuery(BoundStatement $operation, ParsedQueryInsert $statement): InsertQuery
    {
        $source = $statement->source;
        Check::input($source instanceof ParsedSelect, 'The INSERT query requires a simple SELECT.');
        Check::input(
            $statement->with === null && $statement->overriding === null && $statement->conflict === null,
            'A query INSERT requires an ordinary write without conflict modifiers.'
        );
        [$target, $returning] = (new InsertTargetBinding())->lower($operation, $statement);
        $query = (new SelectLowering())->lower($operation, $source, assignment: $target);
        if ($query->distinct) {
            foreach ($query->columns as $position => $column) {
                Check::input(
                    !$column->expression instanceof NullLiteral || ($target->written[$position]->kind ?? null) === ColumnKind::Text,
                    'A DISTINCT insert projection cannot assign an untyped NULL to a non-text column.'
                );
            }
        }

        return new InsertQuery($target, $query, $returning);
    }
}
