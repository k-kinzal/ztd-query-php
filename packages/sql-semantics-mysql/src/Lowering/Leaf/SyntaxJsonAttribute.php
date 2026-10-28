<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Text\Json\Attribute;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute;

/**
 * Interprets an engine attribute before a later token can obscure its refusal.
 *
 * Complete definitions retain their operands and publish the same JSON condition.
 * An unfinished input retains the decoded operand and its completion boundary.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxJsonAttribute
{
    /**
     * Reports a grammar-time condition at the completed JSON operand.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        $failure = $this->rejected($node, $lowering);
        return $failure === null ? null : new ParseFailure($failure->problem, true);
    }

    /**
     * Answers the completed JSON condition when later syntax prevents publication.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if ($node->name !== 'json_attribute') {
            return null;
        }
        $text = $lowering->literals->bytes($node);
        $failure = Attribute::failure($text);
        return $failure === null ? null : new RejectedInput(new RefusedJsonAttribute(new Text($text), $failure->reason, $failure->position, $failure->deep), (new SourceRanges())->span($node)[1] ?? 0);
    }
}
