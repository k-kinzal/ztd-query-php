<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlSemantics\Statement\Node;

/**
 * Retains input boundaries for complete stored-program declarations.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DefinitionBody
{
    /**
     * Retains numeric header and body boundaries independently of any body condition.
     */
    public function source(Node $body, \SqlParser\Parser\Node $name, \SqlParser\Parser\Node $tree, ?\SqlParser\Parser\Node $parameters = null, ?\SqlParser\Parser\Node $returns = null): \SqlSemantics\Platform\MySql\Statement\Routine\Definition\DefinitionSource
    {
        $tokens = $tree->tokens();
        $offset = null;
        if ($body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody) {
            $offset = $tokens[1]->offset;
        } elseif ($body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block) {
            $offset = $tokens[count($tokens) - 1]->offset;
        }
        return new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\DefinitionSource(
            (new \SqlSemantics\Construction\SourceRanges())->span($name)[1] ?? 0,
            $parameters === null ? [] : $this->typeEnds($parameters, $returns),
            $offset,
        );
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
