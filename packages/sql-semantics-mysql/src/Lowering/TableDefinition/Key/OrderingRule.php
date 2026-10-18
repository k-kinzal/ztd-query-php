<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Key\IndexOrdering;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\ExplicitIndexOrder;

/**
 * Separates a refused modern index ordering from a legacy index declaration.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OrderingRule
{
    /**
     * @param list<IndexOption> $options Complete lowered index options
     * @return non-empty-list<KeyPart> The ordinary key parts after the index kind is coherent
     * @throws RejectedInput When explicit ordering has no modern interpretation
     */
    public function parts(Lowering $lowering, Node $node, Node $parts, IndexKind $kind, ?IndexAlgorithm $algorithm, array $options): array
    {
        $rule = new KeyPartRule($lowering);
        $directions = $rule->directions($parts);
        if (!in_array($lowering->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) && $directions !== [] && (new IndexOrdering())->unordered($kind, $algorithm, $options)) {
            $table = $lowering->programName ?? $lowering->columnTable;
            \SqlSemantics\Diagnostic\Check::invariant($table !== null, 'The index owns a completed table identity.');
            throw new RejectedInput(new ExplicitIndexOrder($kind, $directions[0], $table, $algorithm, $options), (new SourceRanges())->span($node)[1] ?? 0, false);
        }
        return $rule->parts($parts);
    }
}
