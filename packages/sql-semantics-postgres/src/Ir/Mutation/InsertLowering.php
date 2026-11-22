<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SelectLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertSelect as ParsedQueryInsert;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;

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
        Check::input($statement->with === null && $statement->overriding === null && $statement->conflict === null
            && $statement->returning === [], 'A complete INSERT requires the ordinary VALUES operation.');
        $target = (new InsertTargetBinding())->lower($operation, $statement);
        $searchPath = $operation->context->searchPath;
        $catalogFirst = ($searchPath[0]->value === 'pg_temp' ? ($searchPath[1]->value ?? null) : $searchPath[0]->value) === 'pg_catalog';
        $expression = new ExpressionLowering();
        $empty = new SourceBinding($operation, null);
        $rows = [];
        foreach ($statement->rows->rows() as $row) {
            $values = [];
            foreach ($row->values as $value) {
                $item = $expression->lower($value, $empty, $catalogFirst);
                Check::input($item instanceof IntegerLiteral || $item instanceof NullLiteral, 'A supplied INSERT value requires an integer or NULL literal.');
                $values[] = new InsertCell($item instanceof NullLiteral ? null : $item);
            }
            $rows[] = new InsertRow($values);
        }

        return new Insert($target, $rows, $profile->grammar);
    }

    /**
     * Publishes the query write after resolving its independent input and writable targets.
     */
    public function lowerQuery(BoundStatement $operation, ParsedQueryInsert $statement): InsertQuery
    {
        $source = $statement->source;
        Check::input($source instanceof Select, 'The INSERT query requires a simple SELECT.');
        Check::input($statement->with === null && $statement->overriding === null && $statement->conflict === null
            && $statement->returning === [], 'A query INSERT requires an ordinary write without conflict modifiers.');
        $query = (new SelectLowering())->lower($operation, $source);
        if ($query->distinct) {
            foreach ($query->columns as $column) {
                Check::input(
                    !$column->expression instanceof NullLiteral,
                    'A DISTINCT insert projection cannot assign an untyped NULL to an integer column.'
                );
            }
        }

        return new InsertQuery((new InsertTargetBinding())->lower($operation, $statement), $query);
    }
}
