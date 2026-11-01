<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TableRule;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;

/**
 * Retains mandatory relation-alias refusals at the completed table production.
 * The refusal precedes later input and name resolution, including in uncalled routines.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxAliases
{
    /**
     * JSON_TABLE requires a nonempty alias before the rest of its statement is read.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if ($node->name === 'derived_table') {
            $form = $lowering->form($node);
            $at = $form->signature === 'derived_table: LATERAL_SYM table_subquery opt_table_alias opt_derived_column_list' ? 2 : 1;
            $alias = (new TableRule($lowering))->alias($form->node($at));

            return $alias === null ? new ParseFailure(new Misuse(MisuseRule::DerivedWithoutAlias)) : null;
        }
        if ($node->name !== 'table_function') {
            return null;
        }
        $alias = (new TableRule($lowering))->alias($lowering->form($node)->node(7));

        return $alias === null || $alias->value === '' ? new ParseFailure(new Misuse(MisuseRule::TableFunctionWithoutAlias), true) : null;
    }
}
