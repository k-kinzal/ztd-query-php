<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Resolves the target and closes literal VALUES at the write boundary.
 *
 * @visibility SqlSemantics\Platform\MySql
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
        Check::input($profile->lexical->equals(new LexicalSettings()), 'This INSERT profile requires the default lexical settings.');
        $into = $statement->into;
        Check::input(!$into->replace && !$into->ignore && $into->priority === null && $statement->alias === null
            && $statement->onDuplicate === [], 'A complete INSERT requires the ordinary VALUES operation.');
        Check::input($into->columns !== null && $into->columns->columns !== [], 'An INSERT requires explicit target columns.');
        $names = [];
        foreach ($into->columns->columns as $column) {
            Check::input($column instanceof ColumnUse, 'An INSERT column must name a declared target slot.');
            $names[] = $column->name->value;
        }
        $target = (new SourceBinding($operation, $into->table))->writeTarget($names);
        $expression = new ExpressionLowering();
        $empty = new SourceBinding($operation, null);
        $rows = [];
        foreach ($statement->rows as $row) {
            $values = [];
            foreach ($row->values as $value) {
                $item = $expression->lower($value, $empty);
                Check::input($item instanceof IntegerLiteral || $item instanceof NullLiteral, 'A supplied INSERT value requires an integer or NULL literal.');
                $values[] = new InsertCell($item instanceof NullLiteral ? null : $item);
            }
            $rows[] = new InsertRow($values);
        }

        return new Insert($target, $rows, $profile->grammar);
    }
}
