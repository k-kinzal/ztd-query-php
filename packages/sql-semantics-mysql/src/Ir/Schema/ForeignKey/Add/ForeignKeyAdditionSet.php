<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyKeyCapacity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\ExplicitKeyChecks;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\IndexAddition;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexLock;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Contract\InnoDbKeyLimit;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Checks native name allocation, physical support and group roles before publication.
 * @visibility SqlSemantics
 */
final class ForeignKeyAdditionSet
{
    /**
     * Establishes the meaning of a full checked native addition group.
     * @param non-empty-list<ForeignKeyAddition> $additions Ordered checked native additions
     * @param list<ForeignKeyNativeKey> $keys Complete prior physical keys
     * @param list<ForeignKeyConstraintIdentity> $constraints Complete prior native symbols
     * @param array<int, IndexAddition> $indexes Explicit additions by action position
     * @param list<int> $foreignSlots Written positions of the added foreign constraints
     * @return list<Identifier> The names of the replaced implicit child indexes
     */
    public function check(
        TableIdentity $owner,
        array $additions,
        array $keys,
        array $constraints,
        GrammarRelease $release,
        IndexChangePolicy $policy,
        bool $ignore,
        ?InnoDbKeyLimit $limit = null,
        array $indexes = [],
        array $foreignSlots = [],
    ): array {
        Check::input($owner->kind === ExternalRelationKind::BaseTable, 'A native foreign-key addition requires a persistent owning table.');
        Check::input(!$ignore || $release === GrammarRelease::MySql5651, 'Only MySQL 5.6.51 admits ALTER IGNORE TABLE.');
        Check::input(
            $policy->algorithm !== IndexAlgorithm::InPlace && $policy->lock !== IndexLock::None,
            'The checked InnoDB profile needs copying and a compatible lock to add a native foreign key.'
        );
        $legacy = (new ForeignKeyRemovalSet())->legacy($release);
        $selected = $fields = $labels = [];
        foreach ($additions as $addition) {
            $selected[] = $addition->generated ? null : $addition->symbol;
            $fields[] = $addition->pairs;
            $labels[] = $addition->generated ? $addition->indexName : $addition->symbol;
        }
        $symbols = (new ForeignKeyConstraintNamespace())->allocate($owner, $constraints, $selected);
        $explicit = (new ExplicitKeyChecks())->requests($owner, $keys, $indexes, $legacy);
        [$created, $replaced, $newNames] = (new ForeignKeyChildIndexes())->allocate($owner, $keys, $fields, $labels, $release, $explicit, $foreignSlots);
        foreach ($indexes as $position => $index) {
            Check::input(
                $newNames[$position]->value === $index->key->identity->name->value,
                'The declared secondary index name differs from native allocation.'
            );
        }
        $layout = new ForeignKeyIndexPrefix();
        $capacity = new ForeignKeyKeyCapacity();
        $nativeLimit = $capacity->effective($release, $limit);
        foreach ($additions as $position => $addition) {
            $this->release($addition, $legacy);
            $capacity->check($addition->pairs, $nativeLimit);
            Check::input($symbols[$position]->value === $addition->symbol->value, 'The foreign-key symbol differs from native allocation.');
            Check::input($created[$position]?->value === $addition->createdIndex?->value, 'The declared foreign-key index effect differs from native allocation.');
            $layout->requireParent($addition->parent, $addition->pairs, $keys, $release);
        }

        return $replaced;
    }

    /**
     * Checks the choices permitted by the specified native release family.
     */
    public function release(ForeignKeyAddition $addition, bool $legacy): void
    {
        if ($legacy) {
            $effects = $addition->effects;
            Check::input($effects->match === ForeignKeyMatch::None, 'Legacy MySQL foreign keys have no MATCH metadata.');
            Check::input(
                $effects->update !== ForeignKeyAction::SetDefault && $effects->delete !== ForeignKeyAction::SetDefault,
                'Legacy InnoDB rejects effective SET DEFAULT on a foreign key.'
            );
        }
        foreach ($addition->pairs as $pair) {
            Check::input(!$legacy || !$pair->descending, 'Legacy MySQL foreign keys do not accept native index direction.');
            if ($pair->domain instanceof ForeignKeyEnumerationDomain) {
                $pair->domain->checkRelease($legacy);
            }
        }
    }
}
