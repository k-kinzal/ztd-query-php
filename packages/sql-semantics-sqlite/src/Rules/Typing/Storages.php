<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Typing;

use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Computes with the sets of storage classes a value can have.
 *
 * Rule: SQLITE-STORAGE-SET-001. A value has one of the storage classes
 * INTEGER, REAL, TEXT and BLOB, or is NULL. A column of an ordinary table
 * can hold the classes its affinity does not convert away: every class under
 * INTEGER, NUMERIC and BLOB affinity; TEXT and BLOB under TEXT affinity;
 * REAL, TEXT and BLOB under REAL affinity. A column of a STRICT table holds
 * only the class of its type, and any class when its type is ANY. A type that depends on a missing
 * input or is invalid has no known set. Source:
 * https://sqlite.org/datatype3.html#type_affinity,
 * https://sqlite.org/stricttables.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Storages
{
    /**
     * Answers the storage classes a type fact admits, or null when the fact fixes no set.
     *
     * @return list<Storage>|null
     */
    public function of(TypeFact $type): ?array
    {
        if ($type instanceof NullOnly) {
            return [];
        }
        if ($type instanceof Choice) {
            $storages = [];
            foreach ($type->alternatives as $alternative) {
                $storages = $this->merge($storages, $this->held($alternative));
            }

            return $storages;
        }
        if ($type instanceof Known) {
            return $this->held($type->descriptor);
        }

        return null;
    }

    /**
     * Answers the storage classes a value of a described type can have.
     *
     * @return list<Storage>
     */
    public function held(object $descriptor): array
    {
        if ($descriptor instanceof Storage) {
            return [$descriptor];
        }
        if (!$descriptor instanceof ColumnDomain) {
            return Storage::cases();
        }

        if ($descriptor->strict && $descriptor->standard && $descriptor->declared !== 'ANY') {
            return match ($descriptor->affinity) {
                Affinity::Integer => [Storage::Integer],
                Affinity::Real => [Storage::Real],
                Affinity::Text => [Storage::Text],
                Affinity::Blob => [Storage::Blob],
                Affinity::Numeric => Storage::cases(),
            };
        }

        return match ($descriptor->affinity) {
            Affinity::Text => [Storage::Text, Storage::Blob],
            Affinity::Real => [Storage::Real, Storage::Text, Storage::Blob],
            Affinity::Integer, Affinity::Numeric, Affinity::Blob => Storage::cases(),
        };
    }

    /**
     * Answers the union of two sets in the fixed order of the storage classes.
     *
     * @param list<Storage> $left
     * @param list<Storage> $right
     * @return list<Storage>
     */
    public function merge(array $left, array $right): array
    {
        $union = [];
        foreach (Storage::cases() as $storage) {
            if (in_array($storage, $left, true) || in_array($storage, $right, true)) {
                $union[] = $storage;
            }
        }

        return $union;
    }

    /**
     * Answers the type fact of a set of storage classes: NULL only, one known class, or a choice.
     *
     * @param list<Storage> $storages
     */
    public function fact(array $storages): TypeFact
    {
        $storages = $this->merge($storages, []);
        if ($storages === []) {
            return new NullOnly();
        }

        return count($storages) === 1 ? new Known($storages[0]) : new Choice($storages);
    }

    /**
     * Answers the type of a value that is one of several values: the union of their classes.
     *
     * An invalid alternative makes the result invalid; an alternative that
     * depends on missing inputs makes the result depend on them.
     *
     * @param list<TypeFact> $alternatives
     */
    public function either(array $alternatives): TypeFact
    {
        $storages = [];
        $missing = [];
        foreach ($alternatives as $alternative) {
            if ($alternative instanceof Invalid) {
                return $alternative;
            }
            if ($alternative instanceof Dependent) {
                array_push($missing, ...$alternative->missing);
            } else {
                $storages = $this->merge($storages, $this->of($alternative) ?? []);
            }
        }

        return $missing === [] ? $this->fact($storages) : new Dependent($missing);
    }

    /**
     * Answers the type of an arithmetic result: REAL when an operand is certainly REAL, NULL when an operand is certainly NULL, else INTEGER or REAL.
     *
     * @param list<TypeFact> $operands
     */
    public function numeric(array $operands): TypeFact
    {
        $real = false;
        foreach ($operands as $operand) {
            $storages = $this->of($operand);
            if ($storages === []) {
                return new NullOnly();
            }
            $real = $real || $storages === [Storage::Real];
        }

        return $real ? new Known(Storage::Real) : new Choice([Storage::Integer, Storage::Real]);
    }

    /**
     * Answers the type of an operator that yields one class unless an operand is certainly NULL.
     *
     * @param list<TypeFact> $operands
     */
    public function strict(Storage $result, array $operands): TypeFact
    {
        foreach ($operands as $operand) {
            if ($operand instanceof NullOnly) {
                return $operand;
            }
        }

        return new Known($result);
    }
}
