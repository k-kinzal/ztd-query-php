<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Decision;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;

/**
 * Typed MergeUpdate effect.
 * @visibility public
 * @example Counting an update decision's assignments
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN MATCHED THEN UPDATE SET id=s.id');
 *     count($statement->merge->actions[0]->assignments) // => 1
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
