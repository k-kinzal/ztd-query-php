<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Optimization;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A MySQL index hint on one table occurrence: USE, IGNORE or FORCE a list of indexes, optionally only for joins,
 * ordering or grouping. `PRIMARY` names the primary key.
 *
 * @visibility public
 * @example Reading the index hints of a table
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT, KEY k (a))'));
 *     $hint = $binder->bind('SELECT a FROM t IGNORE INDEX FOR ORDER BY (k, PRIMARY)')->from->indexHints[0];
 *     [$hint->action, $hint->scope, $hint->indexes] // => [\SqlSemantics\Model\Query\Optimization\IndexHintAction::Ignore, \SqlSemantics\Model\Query\Optimization\IndexHintScope::OrderBy, ['k', 'PRIMARY']]
 */
final class IndexHint
{
    /**
     * @param IndexHintScope|null $scope Operation the hint is restricted to; null applies it to every operation
     * @param list<string> $indexes Index names; only USE takes none, which asks for no index at all
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly IndexHintAction $action,
        public readonly ?IndexHintScope $scope,
        public readonly array $indexes,
    ) {
        Collections::strings($indexes);
        if ($indexes === [] && $action !== IndexHintAction::Use) {
            throw new InvalidStructure('IGNORE and FORCE index hints name at least one index.');
        }
        if (in_array('', $indexes, true)) {
            throw new InvalidStructure('An index hint names indexes by nonempty names.');
        }
    }
}
