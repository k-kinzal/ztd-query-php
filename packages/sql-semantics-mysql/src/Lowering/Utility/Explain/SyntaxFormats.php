<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Explain;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UnknownExplainFormat;

/**
 * Preserves a completed EXPLAIN format refusal before later malformed operands.
 *
 * The reduction supplies a decoded name; the published notice retains no syntax tree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxFormats
{
    /**
     * Returns the aborting format diagnostic of a completed FORMAT assignment.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if (!in_array($node->name, ['opt_explain_format', 'opt_extended_describe'], true) || count($node->children) !== 3) {
            return null;
        }
        $form = $lowering->form($node);
        $name = $lowering->names->identifier($form->node(2));
        $refusal = (new ExplainFacts())->refusal($lowering->profile->grammar, strtoupper($name->value), false, false);

        return $refusal === null ? null : new ParseFailure(new UnknownExplainFormat($name), true);
    }
}
