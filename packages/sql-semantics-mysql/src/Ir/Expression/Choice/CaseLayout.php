<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Expression\Choice;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\DerivedSource;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralSlot;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\From\JoinKind;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Derives a signed CASE result heading from its checked occurrence and release.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CaseLayout
{
    /**
     * Derives native field storage and heading nullability for the checked case.
     * @return array{storage:IntegerStorage|null,nullable:bool}
     */
    public function resolve(SignedCase $case, GrammarRelease $release, TableSource|Join|null $source): array
    {
        $inputs = $source === null ? [] : $this->occurrences($source);
        [$storage, $nullable] = $this->result($case, $release, $inputs);

        return ['storage' => $storage, 'nullable' => $nullable];
    }

    /**
     * Retains null extension along the owning occurrence path.
     * @return list<array{TableSource|DerivedSource,bool}>
     */
    public function occurrences(TableSource|DerivedSource|Join $source, bool $extended = false): array
    {
        if (!$source instanceof Join) {
            return [[$source, $extended]];
        }

        return array_merge(
            $this->occurrences($source->left, $extended || $source->kind === JoinKind::Right),
            $this->occurrences($source->right, $extended || $source->kind === JoinKind::Left),
        );
    }

    /**
     * Aggregates the WHEN result types and any explicit ELSE in native order.
     * @param list<array{TableSource|DerivedSource,bool}> $inputs
     * @return array{IntegerStorage|null,bool}
     */
    public function result(SignedCase $case, GrammarRelease $release, array $inputs): array
    {
        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
        $nullable = $case->otherwise === null;
        $results = [];
        foreach ($case->arms as $arm) {
            $results[] = $this->operand($arm->result, $release, $inputs);
            if ($release === GrammarRelease::MySql5651) {
                $nullable = $nullable || $this->operand($arm->decision, $release, $inputs)[1];
            }
        }
        if ($case->otherwise !== null) {
            $results[] = $this->operand($case->otherwise, $release, $inputs);
        }
        if ($release === GrammarRelease::MySql5651 && $case->subject !== null) {
            $nullable = $nullable || $this->operand($case->subject, $release, $inputs)[1];
        }
        [$storage, $firstNullable] = array_shift($results);
        $nullable = $nullable || $firstNullable;
        foreach ($results as [$next, $canBeNull]) {
            $storage = $this->merge($storage, $next, $legacy);
            $nullable = $nullable || $canBeNull;
        }

        return [$storage, $nullable];
    }

    /**
     * Looks up a leaf or recurses through a complete nested result.
     * @param list<array{TableSource|DerivedSource,bool}> $inputs
     * @return array{IntegerStorage|null,bool}
     */
    public function operand(
        BooleanLiteral|ColumnReference|IntegralColumnReference|NullLiteral|RangeIntegerLiteral|IntegerMembership|SignedCase $operand,
        GrammarRelease $release,
        array $inputs,
    ): array {
        return match (true) {
            $operand instanceof NullLiteral => [null, true],
            $operand instanceof ColumnReference, $operand instanceof IntegralColumnReference => $this->field($operand, $inputs),
            $operand instanceof SignedCase => $this->result($operand, $release, $inputs),
            $operand instanceof IntegerMembership => [IntegerStorage::Wide, $this->membershipNullable($operand, $release, $inputs)],
            $operand instanceof BooleanLiteral, $operand instanceof RangeIntegerLiteral => [IntegerStorage::Wide, false],
        };
    }

    /**
     * Reads the exact bound field storage and effective native NULL flag.
     * @param list<array{TableSource|DerivedSource,bool}> $inputs
     * @return array{IntegerStorage,bool}
     */
    public function field(ColumnReference|IntegralColumnReference $field, array $inputs): array
    {
        [$source, $extended] = $inputs[$field->occurrence];
        $type = $source instanceof TableSource ? $source->columnTypes[$field->slot] : $source->columnType($field->slot);
        if ($type instanceof IntegralSlot) {
            return [$type->storage, $extended || $type->nullable];
        }
        Check::invariant($type instanceof ColumnKind, 'A signed CASE read has the checked integral source role.');

        return [IntegerStorage::Standard, $extended || $type->nullable()];
    }

    /**
     * Accounts for NULL operands in the native membership result.
     * @param list<array{TableSource|DerivedSource,bool}> $inputs
     */
    public function membershipNullable(IntegerMembership $test, GrammarRelease $release, array $inputs): bool
    {
        foreach ([$test->subject, ...$test->alternatives] as $item) {
            if ($this->operand($item, $release, $inputs)[1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Folds one pair using the ordered native type rule for this cohort.
     */
    public function merge(?IntegerStorage $prior, ?IntegerStorage $next, bool $legacy): ?IntegerStorage
    {
        if ($legacy && $next === IntegerStorage::Medium) {
            if ($prior === null) {
                return IntegerStorage::Wide;
            }
            if ($prior === IntegerStorage::Wide) {
                return IntegerStorage::Standard;
            }
        }
        if ($prior === null || ($next !== null && $next->bytes() > $prior->bytes())) {
            return $next;
        }

        return $prior;
    }
}
