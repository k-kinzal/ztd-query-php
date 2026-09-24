<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Decision;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;

/**
 * Typed MergeNothing effect.
 * @visibility public
 * @example Inspecting a decision without effect
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN MATCHED THEN DO NOTHING');
 *     $statement->merge->actions[0] instanceof \SqlSemantics\Model\Write\Decision\MergeNothing // => true
 */
final class MergeNothing extends \SqlSemantics\Model\Write\MergeAction
{
    /**

     */
    public function __construct(MatchKind $match, ?Expression $condition, Node $source)
    {
        parent::__construct($match, $condition, $source);
    }

    #[Override]
    protected function operation(): ActionKind
    {
        return ActionKind::Nothing;
    }
}
