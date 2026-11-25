<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyKeyColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexDependencyKeys;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Checks the separate physical support roles of native child and parent keys.
 * @visibility SqlSemantics
 */
final class ForeignKeyIndexPrefix
{
    /**
     * Collects the original physical keys on exactly one persistent owner.
     * @param list<ForeignKeyNativeKey> $catalog Original physical keys
     * @return array<array-key, ForeignKeyNativeKey>
     */
    public function keys(TableIdentity $owner, array $catalog): array
    {
        $case = new NativeIdentifierCase();
        $relation = $owner->quoted();
        $keys = [];
        foreach ($catalog as $key) {
            if ($key->identity->owner->quoted() !== $relation) {
                continue;
            }
            $name = $case->fold($key->identity->name->value);
            Check::input(!isset($keys[$name]), 'The native physical catalog has a repeated index name.');
            $keys[$name] = $key;
        }

        return $keys;
    }

    /**
     * Projects the full ordered child fields and their requested physical directions.
     * @param non-empty-list<ForeignKeyPair> $pairs Ordered foreign-key field pairs
     * @return non-empty-list<array{string, bool}>
     */
    public function child(array $pairs): array
    {
        $case = new NativeIdentifierCase();

        $fields = [];
        foreach ($pairs as $pair) {
            $fields[] = [$case->fold($pair->child->value), $pair->descending];
        }

        return $fields;
    }

    /**
     * Projects the full original physical child key prefix.
     * @return list<array{string, bool}>
     */
    public function physical(ForeignKeyNativeKey $key): array
    {
        return $this->columns($key->fields);
    }

    /**
     * Projects the exact native directional prefix of whole fields.
     * @param list<ForeignKeyKeyColumn> $columns The ordered physical fields
     * @return list<array{string, bool}>
     */
    public function columns(array $columns): array
    {
        $case = new NativeIdentifierCase();

        $fields = [];
        foreach ($columns as $term) {
            $fields[] = [$case->fold($term->field->value), $term->descending];
        }

        return $fields;
    }

    /**
     * Requires an eligible original index on the exact resolved parent owner.
     * @param non-empty-list<ForeignKeyPair> $pairs Ordered foreign-key field pairs
     * @param list<ForeignKeyNativeKey> $catalog Original keys supplied at the construction boundary
     * @throws InvalidConstruction When the original parent has no supporting key
     */
    public function requireParent(TableIdentity $parent, array $pairs, array $catalog, GrammarRelease $release, bool $beforeSelfRebuild = false): void
    {
        $requested = [];
        foreach ($pairs as $pair) {
            $requested[] = $pair->parent;
        }
        if (!$this->supports($parent, $requested, $catalog, $release, true, $beforeSelfRebuild)) {
            throw new InvalidConstruction('A native foreign key requires original physical support on the resolved parent.');
        }
    }

    /**
     * Checks settled native leading fields for one side of a foreign key.
     * @param non-empty-list<\SqlSemantics\Platform\MySql\Ir\Identifier> $requested Ordered fields of the affected endpoint
     * @param list<ForeignKeyNativeKey> $catalog The physical keys of the corresponding layout
     */
    public function supports(TableIdentity $owner, array $requested, array $catalog, GrammarRelease $release, bool $parent, bool $beforeSelfRebuild = false): bool
    {
        [$exact, $hidden] = (new IndexDependencyKeys())->policy($parent, $release);
        $keys = $this->keys($owner, $catalog);
        $cluster = [];
        $case = new NativeIdentifierCase();
        if ($hidden) {
            foreach ($keys as $key) {
                if ($key->identity->role->clustered()) {
                    foreach ($key->fields as $field) {
                        $cluster[] = $case->fold($field->field->value);
                    }
                    break;
                }
            }
        }
        $required = [];
        foreach ($requested as $field) {
            $required[] = $case->fold($field->value);
        }
        foreach ($keys as $key) {
            $fields = [];
            foreach ($key->fields as $field) {
                $fields[] = $case->fold($field->field->value);
            }
            if ($this->parentMatch($required, $key, $fields, $cluster, $exact && !$beforeSelfRebuild)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks the release-specific original parent key before publishing a child constraint.
     * @param non-empty-list<string> $required Ordered corresponding parent columns
     * @param list<string> $leading Leading physical columns before a prefix or expression
     * @param list<string> $cluster Full clustered columns available as native hidden terms
     */
    public function parentMatch(array $required, ForeignKeyNativeKey $key, array $leading, array $cluster, bool $exact): bool
    {
        if (!$key->referenceKey) {
            return false;
        }
        if ($exact) {
            return $key->identity->role !== IndexRole::Ordinary && $key->full && $leading === $required;
        }
        if ($key->full && !$key->identity->role->clustered()) {
            foreach ($cluster as $hidden) {
                if (!in_array($hidden, $leading, true)) {
                    $leading[] = $hidden;
                }
            }
        }

        return array_slice($leading, 0, count($required)) === $required;
    }
}
