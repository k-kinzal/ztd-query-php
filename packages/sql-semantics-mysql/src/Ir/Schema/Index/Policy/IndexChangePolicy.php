<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The algorithm and concurrency request of an ordinary InnoDB index change.
 *
 * The owning index change closes the request against its table and operation role.
 *
 * @visibility public
 * @example Retaining a shared-lock copy request
 *     $policy = new \SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy(\SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexAlgorithm::Copy, \SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexLock::Shared);
 *     $policy->sql() // => ' ALGORITHM=COPY LOCK=SHARED'
 */
final class IndexChangePolicy
{
    use Snapshot;

    /**
     * Stores the native physical options with their default requests.
     */
    public function __construct(
        public readonly IndexAlgorithm $algorithm = IndexAlgorithm::Default,
        public readonly IndexLock $lock = IndexLock::Default,
    ) {
    }

    /**
     * Applies the native compatibility rules for the resolved owner and operation.
     */
    public function supports(ExternalRelationKind $owner, bool $removesPrimary = false): bool
    {
        return match ($owner) {
            ExternalRelationKind::TemporaryTable => $this->algorithm !== IndexAlgorithm::InPlace,
            ExternalRelationKind::BaseTable => $removesPrimary
                ? $this->algorithm !== IndexAlgorithm::InPlace && $this->lock !== IndexLock::None
                : $this->algorithm !== IndexAlgorithm::Copy || $this->lock !== IndexLock::None,
            ExternalRelationKind::View => false,
        };
    }

    /**
     * Emits the effective native requests in a fixed order.
     */
    public function sql(bool $commaSeparated = false): string
    {
        $separator = $commaSeparated ? ', ' : ' ';
        $algorithm = $this->algorithm === IndexAlgorithm::Default ? '' : $separator . 'ALGORITHM=' . $this->algorithm->value;
        $lock = $this->lock === IndexLock::Default ? '' : $separator . 'LOCK=' . $this->lock->value;

        return $algorithm . $lock;
    }
}
