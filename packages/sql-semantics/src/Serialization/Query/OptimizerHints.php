<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Model\Query\Optimization;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Writes optimizer directives from classified operands rather than retained comment text.
 * @visibility SqlSemantics
 */
final class OptimizerHints
{
    /**
     * @param list<Optimization\OptimizerHint> $hints
     */
    public static function write(array $hints): Tree
    {
        if ($hints === []) {
            return new Tree('optimizer-hints', []);
        }
        $directives = array_map(static fn (Optimization\OptimizerHint $hint): string => match (true) {
            $hint instanceof Optimization\MaxExecutionTime => 'MAX_EXECUTION_TIME(' . $hint->milliseconds . ')',
            default => throw new InvalidStructure('Unclassified optimizer directive.'),
        }, $hints);
        return new Tree('optimizer-hints', [new Atom('hint', '/*+ ' . implode(' ', $directives) . ' */')]);
    }
}
