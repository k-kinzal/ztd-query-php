<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Decision;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\RowShape;
use SqlSemantics\Model\Write\InputRow;

/**
 * Typed MergeRowInsertion effect.
 * @visibility public
 */
final class MergeRowInsertion extends \SqlSemantics\Model\Write\MergeAction
{
    /**
     * Each successful match inserts exactly one row into its destination columns.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(MatchKind $match, ?Expression $condition, Node $source, public readonly \SqlSemantics\Model\Write\Insertion $insertion, public readonly InputRow $row)
    {
        if ($match !== MatchKind::MissingTarget) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A MERGE INSERT requires a missing target.');
        }
        RowShape::insertion($insertion, count($row->items), $row->dialect);
        parent::__construct($match, $condition, $source);
    }

    #[Override]
    protected function operation(): ActionKind
    {
        return ActionKind::Insert;
    }
}
