<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange\Partition;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Partition\DefinitionShape;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionMethod;

/**
 * Preserves legacy bound conditions before an unfinished partition list.
 *
 * Only transient input reading uses this state. Complete statements retain
 * their method and definitions and derive the same conditions themselves.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DefinitionPrefix
{
    private ?PartitionMethod $method = null;

    /**
     * Observes a method and its completed definitions in grammar order.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if (!in_array($lowering->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return null;
        }
        try {
            if ($node->name === 'part_type_def') {
                $this->method = (new PartitionRule($lowering))->method($lowering->form($node));
            } elseif ($node->name === 'part_definition' && $this->method !== null) {
                $definition = (new DefinitionRule($lowering))->definition($node);
                $problem = (new DefinitionShape())->problem($definition, $this->method, null);
                return $problem === null ? null : new ParseFailure($problem, true);
            } elseif ($node->name === 'partition') {
                $this->method = null;
            }
        } catch (RejectedInput $failure) {
            return new ParseFailure($failure->problem, $failure->aborts);
        }
        return null;
    }
}
