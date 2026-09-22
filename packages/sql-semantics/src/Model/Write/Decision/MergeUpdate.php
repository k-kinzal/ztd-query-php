<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Decision;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;

/**
 * Typed MergeUpdate effect.
 * @visibility public
 */
final class MergeUpdate extends \SqlSemantics\Model\Write\MergeAction
{
    /**
     * @var non-empty-list<\SqlSemantics\Model\Write\Assignment> Validated ordered operands
     */
    public readonly array $assignments;

    /**
     * @param list<\SqlSemantics\Model\Write\Assignment> $assignments
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(MatchKind $match, ?Expression $condition, Node $source, array $assignments)
    {
        \SqlSemantics\Model\Validation\Collections::objects($assignments, \SqlSemantics\Model\Write\Assignment::class);
        if ($assignments === [] || $match === MatchKind::MissingTarget) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A MERGE UPDATE needs an existing target and assignments.');
        }
        parent::__construct($match, $condition, $source);
        $this->assignments = \SqlSemantics\Model\Validation\Collections::nonEmpty($assignments);
    }

    #[Override]
    protected function operation(): ActionKind
    {
        return ActionKind::Update;
    }
}
