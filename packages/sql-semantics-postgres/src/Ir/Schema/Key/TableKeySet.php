<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Key;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Closes the table's physical key effects in PostgreSQL's index creation order.
 *
 * @visibility SqlSemantics
 */
final class TableKeySet
{
    /**
     * Retains the effective primary constraint and the ordered distinct unique effects.
     * @param list<UniqueKey> $unique
     * @param array<array-key, true> $fields The owning table's complete physical heading
     * @return array{?PrimaryKey,list<UniqueKey>}
     */
    public static function close(Identifier $table, array $fields, ?PrimaryKey $primary, array $unique): array
    {
        $retained = [];
        foreach ($unique as $candidate) {
            foreach ([...$candidate->keys, ...$candidate->included] as $field) {
                Check::input(isset($fields[$field->value]), 'A unique key reference must select a declared column.');
            }
            if ($primary !== null && self::same($primary, $candidate)) {
                if ($primary->name === null && $candidate->name !== null) {
                    $primary = new PrimaryKey($primary->keys, $primary->included, $candidate->name, $primary->timing);
                }
                continue;
            }
            foreach ($retained as $index => $prior) {
                if (self::same($prior, $candidate)) {
                    if ($prior->name === null && $candidate->name !== null) {
                        $retained[$index] = $candidate;
                    }
                    continue 2;
                }
            }
            $retained[] = $candidate;
        }
        $claimed = [$table->value => true];
        foreach ($primary === null ? $retained : [$primary, ...$retained] as $constraint) {
            if ($constraint->name !== null) {
                $name = $constraint->name->value;
                Check::input(!isset($claimed[$name]), 'Retained table keys must use unused explicit index names.');
                $claimed[$name] = true;
            }
        }

        return [$primary, array_values($retained)];
    }

    /**
     * Compares exactly the native attributes that distinguish one supported index effect.
     */
    public static function same(PrimaryKey|UniqueKey $prior, UniqueKey $candidate): bool
    {
        $priorNullsEqual = $prior instanceof UniqueKey && $prior->nullsNotDistinct;

        return $priorNullsEqual === $candidate->nullsNotDistinct && $prior->timing === $candidate->timing
            && self::sameReferences($prior->keys, $candidate->keys) && self::sameReferences($prior->included, $candidate->included);
    }

    /**
     * Compares ordered decoded references within the checked parent heading.
     * @param list<Identifier> $first
     * @param list<Identifier> $second
     */
    public static function sameReferences(array $first, array $second): bool
    {
        if (count($first) !== count($second)) {
            return false;
        }
        foreach ($first as $index => $field) {
            if ($field->value !== $second[$index]->value) {
                return false;
            }
        }

        return true;
    }
}
