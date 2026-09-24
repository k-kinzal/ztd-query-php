<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Decision;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;

/**
 * Typed MergeInsertDefaults effect.
 * @visibility public
 * @example Inspecting a default-row insertion
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE s(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN NOT MATCHED THEN INSERT DEFAULT VALUES');
 *     $statement->merge->actions[0] instanceof \SqlSemantics\Model\Write\Decision\MergeInsertDefaults // => true
 */
final class MergeInsertDefaults extends \SqlSemantics\Model\Write\MergeAction
{
    /**

     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(MatchKind $match, ?Expression $condition, Node $source, public readonly \SqlSemantics\Model\Write\Insertion $insertion)
    {
        if ($match !== MatchKind::MissingTarget) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A MERGE INSERT needs a missing target.');
        }
        parent::__construct($match, $condition, $source);
    }

    #[Override]
    protected function operation(): ActionKind
    {
        return ActionKind::Insert;
    }
}
