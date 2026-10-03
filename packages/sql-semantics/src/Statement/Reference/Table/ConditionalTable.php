<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Table;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Snapshot;

/**
 * A relation name declared in a later searched schema while an earlier schema is not completely known.
 *
 * An undeclared relation of the earlier schema would be found first, so the
 * known declaration is a candidate and not the resolution.
 *
 * @visibility public
 * @example Keeping a declaration that an unknown earlier schema could shadow
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $declarations = $semantics->context([$semantics->analyze('CREATE TABLE main.t (a INTEGER)')], false);
 *     $query = $semantics->analyze('SELECT a FROM t', $declarations);
 *     count($query->facts->relation($query->inputRelation())->table->candidates) // => 1
 */
final class ConditionalTable implements TableResolution
{
    use Snapshot;

    /**
     * @var non-empty-list<Table> The declarations found in the later schema
     */
    public readonly array $candidates;

    /**
     * @param list<Table> $candidates The declarations found in the later schema; at least one
     * @param UndeclaredRelation $missing The possibly existing relation of an earlier schema
     */
    public function __construct(array $candidates, public readonly UndeclaredRelation $missing)
    {
        $this->candidates = Check::listOf($candidates, Table::class, 'A conditional table has at least one candidate.', 1);
    }
}
