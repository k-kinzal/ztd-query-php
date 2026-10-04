<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition\Query;

use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;

/**
 * The affinity SQLite derives for a result expression; a working value of one derivation.
 *
 * SQLite keeps two states besides the five affinities: no affinity at all
 * (`SQLITE_AFF_NONE`, the affinity of a literal or an operator), and the
 * flexible numeric affinity (`SQLITE_AFF_FLEXNUM`) a CAST gets in a compound
 * query, which counts as numeric but never equals the affinity of a type
 * name. An affinity is not determined when it depends on a declaration the
 * context lacks or on a name that did not resolve.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DerivedAffinity
{
    /**
     * @param Affinity|null $affinity The affinity, or null for no affinity
     * @param bool $flexible Whether the affinity is the flexible numeric one; implies the NUMERIC affinity
     * @param bool $determined Whether the affinity is known; a value that is not determined has no affinity
     */
    public function __construct(public readonly ?Affinity $affinity = null, public readonly bool $flexible = false, public readonly bool $determined = true)
    {
    }

    /**
     * Tells whether the affinity is NUMERIC, INTEGER, REAL or flexible numeric: at least NUMERIC in the order SQLite ranks affinities.
     */
    public function numeric(): bool
    {
        return $this->flexible || $this->affinity === Affinity::Numeric || $this->affinity === Affinity::Integer || $this->affinity === Affinity::Real;
    }

    /**
     * Tells whether the affinity is at least TEXT in the order SQLite ranks affinities: any affinity but BLOB and none.
     */
    public function typed(): bool
    {
        return $this->affinity !== null && $this->affinity !== Affinity::Blob;
    }
}
