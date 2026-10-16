<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyStructure;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

/**
 * Reports declaration-kind contradictions before a public statement is constructed.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DefinitionBody
{
    /**
     * Preserves the typed header when a completed body contradicts its declaration kind.
     *
     * @param list<int> $typeEnds The completion offsets for preceding parameter and return declarations
     * @throws RejectedInput When no declaration can contain the body
     */
    public function check(Node $body, ProgramKind $kind, QualifiedName $name, ?Account $definer, int $end, ?ParameterList $parameters = null, ?DeclaredName $returns = null, ?CollationName $collation = null, array $typeEnds = [], int $nameEnd = 0): void
    {
        $problem = (new BodyStructure())->problem($body, $kind, $name);
        if ($problem !== null) {
            throw new RejectedInput(new RefusedProgramBody($name, $kind, $problem, $definer, $parameters, $returns, $collation, $typeEnds, $nameEnd), $end, false);
        }
    }

    /**
     * Orders parameter completions and the optional return declaration without retaining their trees.
     *
     * @return list<int>
     */
    public function typeEnds(\SqlParser\Parser\Node $parameters, ?\SqlParser\Parser\Node $returns = null): array
    {
        $ranges = new \SqlSemantics\Construction\SourceRanges();
        $pending = [$parameters];
        $ends = [];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (in_array($node->name, ['sp_fdparam', 'sp_pdparam'], true)) {
                $ends[] = $ranges->span($node)[1] ?? 0;
                continue;
            }
            foreach (array_reverse($node->children) as $child) {
                if ($child instanceof \SqlParser\Parser\Node) {
                    $pending[] = $child;
                }
            }
        }
        if ($returns !== null) {
            $ends[] = $ranges->span($returns)[1] ?? 0;
        }
        return $ends;
    }
}
