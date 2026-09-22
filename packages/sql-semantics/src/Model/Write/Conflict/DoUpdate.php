<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Write\Assignment;

/**
 * A conflict UPDATE requires ordered assignments and may filter conflicting rows.
 * @visibility public
 */
final class DoUpdate extends \SqlSemantics\Model\Write\ConflictAction
{
    /**
     * @var non-empty-list<Assignment> Validated ordered operands
     */
    public readonly array $assignments;

    /**
     * @param list<Assignment> $assignments
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Target $target, array $assignments, public readonly ?Expression $where, Node $source)
    {
        \SqlSemantics\Model\Validation\Collections::objects($assignments, Assignment::class);
        if ($assignments === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A conflict UPDATE requires assignments.');
        }
        parent::__construct($target, $source);
        $this->assignments = \SqlSemantics\Model\Validation\Collections::nonEmpty($assignments);
    }

    #[Override]
    protected function operation(): ActionKind
    {
        return ActionKind::Update;
    }
}
