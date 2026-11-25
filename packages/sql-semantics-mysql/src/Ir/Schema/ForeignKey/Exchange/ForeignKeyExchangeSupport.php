<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyKeyColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAddition;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyIndexPrefix;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Batch\AutoIncrementRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\ForeignKeyIndexSide;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Checks the original removal targets and the resulting physical support.
 * @visibility SqlSemantics
 */
final class ForeignKeyExchangeSupport
{
    /**
     * Checks exact removals before allocating new child keys in the surviving namespace.
     * @param list<ForeignKeyNativeKey> $catalog Original keys on the owner and referenced tables
     * @param list<IndexRemovalTarget> $targets Ordered original physical removals
     * @return array{list<ForeignKeyNativeKey>, array<array-key, true>}
     */
    public function survivors(TableIdentity $owner, array $catalog, array $targets, IndexChangePolicy $policy): array
    {
        if ($targets === []) {
            return [$catalog, []];
        }
        $layout = new ForeignKeyIndexPrefix();
        $keys = $layout->keys($owner, $catalog);
        Check::input(count($keys) <= 64, 'The original child catalog exceeds the native index limit.');
        $case = new NativeIdentifierCase();
        $removed = [];
        $clustered = false;
        foreach ($targets as $target) {
            $identity = $target->index;
            $name = $case->fold($identity->name->value);
            $original = $keys[$name] ?? null;
            Check::input(
                $identity->owner->kind === $owner->kind && $identity->owner->quoted() === $owner->quoted()
                && $original !== null && $original->identity->name->value === $identity->name->value
                && $original->identity->role === $identity->role && $original->referenceKey && $original->full
                && !isset($removed[$name]),
                'A mixed physical removal needs one distinct original whole B-tree on the altered table.'
            );
            Check::input(!$clustered || !$identity->role->clustered(), 'The altered table has at most one original clustered identity.');
            $clustered = $clustered || $identity->role->clustered();
            $removed[$name] = true;
            if ($target->autoIncrement !== null) {
                $increment = $target->autoIncrement;
                Check::input(
                    ($original->fields[0] ?? null)?->field->value === $increment->field->value,
                    'A removed generating key must lead with the declared AUTO_INCREMENT field.'
                );
                foreach ($increment->alternates as $alternate) {
                    $previous = $keys[$case->fold($alternate->value)] ?? null;
                    Check::input(
                        $previous !== null && $previous->referenceKey
                        && ($previous->fields[0] ?? null)?->field->value === $increment->field->value,
                        'An original generating backup must identify a leading native key.'
                    );
                }
            }
        }
        Check::input($policy->supports($owner->kind, $clustered), 'The algorithm and lock cannot remove the selected native key.');
        $retained = [];
        foreach ($catalog as $key) {
            if ($key->identity->owner->quoted() !== $owner->quoted() || !isset($removed[$case->fold($key->identity->name->value)])) {
                $retained[] = $key;
            }
        }

        return [$retained, $removed];
    }

    /**
     * Checks the supplied sides against the known original constraint identities.
     * @param list<ForeignKeyIndexSide> $sides Complete retained sides on the owner
     * @param list<ForeignKeyConstraintIdentity> $original Original constraint namespace of the altered database
     * @param list<ForeignKeyRemoval> $removed Exact original outgoing symbols removed by the group
     */
    public function retains(TableIdentity $owner, array $sides, array $original, array $removed): void
    {
        $table = $owner->quoted();
        $known = $outgoing = $seen = $drops = [];
        foreach ($removed as $removal) {
            Check::invariant($removal->name !== null, 'A persistent constraint removal names the original symbol.');
            $drops[$removal->name->value] = true;
        }
        foreach ($original as $identity) {
            $origin = $identity->owner->quoted();
            $name = $identity->symbol->value;
            $known[$origin][$name] = true;
            if ($origin === $table && !isset($drops[$name])) {
                $outgoing[$name] = $name;
            }
        }
        foreach ($sides as $side) {
            $identity = $side->constraint;
            $origin = $identity->owner->quoted();
            $name = $identity->symbol->value;
            $sameSchema = $identity->owner->name->schema->value === $owner->name->schema->value;
            Check::input(
                (!$sameSchema || isset($known[$origin][$name]))
                && ($origin !== $table || !isset($drops[$name]))
                && !isset($seen[$origin][$name][(int) $side->parent]),
                'A retained foreign-key side needs one original constraint identity and role.'
            );
            $seen[$origin][$name][(int) $side->parent] = true;
            if (!$side->parent) {
                Check::input($origin === $table && isset($outgoing[$name]), 'The retained child side must belong to the altered table.');
            }
        }
        foreach ($outgoing as $name) {
            Check::input(isset($seen[$table][$name][0]), 'The supplied layout must cover each retained original child side.');
        }
    }

    /**
     * Closes surviving sides against the final physical catalog.
     * @param list<ForeignKeyNativeKey> $retained Keys following the explicit removals
     * @param list<Identifier> $replaced Implicit keys superseded by new keys
     * @param non-empty-list<ForeignKeyAddition> $additions New foreign constraints
     * @param array<int, IndexAddition> $indexes New explicit physical keys
     * @param non-empty-list<IndexRemovalTarget> $targets Exact original physical removals
     * @param array<array-key, true> $removed Folded explicitly removed names
     * @param list<ForeignKeyIndexSide> $sides Existing sides required after removal
     */
    public function check(
        TableIdentity $owner,
        array $retained,
        array $replaced,
        array $additions,
        array $indexes,
        array $targets,
        array $removed,
        array $sides,
        GrammarRelease $release,
    ): void {
        $case = new NativeIdentifierCase();
        $superseded = [];
        foreach ($replaced as $name) {
            $superseded[$case->fold($name->value)] = true;
        }
        $final = [];
        foreach ($retained as $key) {
            if ($key->identity->owner->quoted() === $owner->quoted() && !isset($superseded[$case->fold($key->identity->name->value)])) {
                $final[] = $key;
            }
        }
        $new = $this->introduced($owner, $additions, $indexes);
        $leading = [];
        foreach ($new as $key) {
            $final[] = $key;
            $leading[$case->fold($key->fields[0]->field->value)] = true;
        }
        (new AutoIncrementRemovalSet())->check($targets, $removed, $leading);
        $layout = new ForeignKeyIndexPrefix();
        foreach ($sides as $side) {
            Check::input(
                $layout->supports($owner, $side->fields, $final, $release, $side->parent),
                'The resulting physical keys must support every retained native foreign-key side.'
            );
        }
        foreach ($additions as $addition) {
            if ($addition->parent->quoted() === $owner->quoted()) {
                $fields = [];
                foreach ($addition->pairs as $pair) {
                    $fields[] = $pair->parent;
                }
                Check::input(
                    $layout->supports($owner, $fields, $final, $release, true),
                    'The resulting physical keys must support the new self-referenced parent.'
                );
            }
        }
    }

    /**
     * Projects the introduced explicit and automatic native backing keys.
     * @param non-empty-list<ForeignKeyAddition> $additions New foreign constraints
     * @param array<int, IndexAddition> $indexes New explicit physical keys
     * @return list<ForeignKeyNativeKey>
     */
    public function introduced(TableIdentity $owner, array $additions, array $indexes): array
    {
        $new = [];
        foreach ($indexes as $index) {
            $new[] = $index->key;
        }
        foreach ($additions as $addition) {
            if ($addition->createdIndex === null) {
                continue;
            }
            $fields = [];
            foreach ($addition->pairs as $pair) {
                $fields[] = new ForeignKeyKeyColumn($pair->child, $pair->descending);
            }
            $identity = new IndexIdentity($owner, $addition->createdIndex, IndexRole::Ordinary);
            $new[] = new ForeignKeyNativeKey($identity, $fields, true, true, true);
        }

        return $new;
    }
}
