<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Result;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Resolves set columns from the returned field types of their operands.
 *
 * A temporal NULLIF evaluates as a temporal value but contributes a string to
 * a set operation. Modern sets write such values in the connection collation;
 * legacy sets retain their numeric coercibility and latin1 collation. BIT
 * fields remain BIT when combined with one another. Legacy BIGINT
 * fields added after the first operand contribute at least their storage width,
 * preserving wider expression bounds. The initial operand keeps its own width.
 * Legacy releases allocate the integer storage width when replacing a leading NULL
 * field. Verified through SQL on MySQL 5.6, 5.7, 8.0 and 8.4.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CombinedColumns
{
    /**
     * Settles the operands before the set's temporary columns are allocated.
     *
     * @param list<Domain> $domains The operand column types
     * @param list<\SqlSemantics\Statement\Shape\Field|null> $fields Original projection facts, when available
     */
    public function resolve(array $domains, Derivation $derivation, array $fields = []): ?Domain
    {
        $present = array_values(array_filter($domains, static fn (Domain $domain): bool => $domain->kind !== Kind::Null));
        if ($present !== [] && count(array_filter($present, static fn (Domain $domain): bool => $domain->field === Field::Bit)) === count($present)) {
            return new Domain(Kind::Bit, Field::Bit, max(array_map(static fn (Domain $domain): int => $domain->length, $present)), 0, true);
        }
        $legacy = in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $operands = array_map(fn (Domain $domain): Domain => $this->operand($domain, $derivation, $legacy), $domains);
        $result = (new Aggregation(new Collations(Settings::of($derivation->context)->connection)))->of($operands, 'UNION', $derivation);
        $result = $result === null ? null : $this->floating($result, $present, $legacy);
        return $legacy && $result !== null ? (new LegacySetColumns())->resolve($result, $operands, $fields) : $result;
    }

    /**
     * Retains fixed fractional digits when floating fields combine. Legacy
     * matching DOUBLE fields report their storage width when no scale is fixed.
     * Fixed scales above 17 become unfixed in legacy temporary fields, observed
     * with AVG(NULL) at every precision increment from 15 through 30.
     *
     * @param list<Domain> $operands The non-NULL operand fields
     */
    public function floating(Domain $result, array $operands, bool $legacy): Domain
    {
        if ($result->kind !== Kind::Double || $operands === [] || count(array_filter($operands, static fn (Domain $domain): bool => $domain->kind === Kind::Double)) !== count($operands)) {
            return $result;
        }
        $scale = max(array_map(static fn (Domain $domain): int => $domain->decimals, $operands));
        $same = count(array_filter($operands, static fn (Domain $domain): bool => $domain->field === Field::Double)) === count($operands);
        if ($legacy && $same && $scale > 17) {
            return Domain::double(22);
        }
        if ($scale < Domain::NOT_FIXED) {
            $length = max(array_map(static fn (Domain $domain): int => $domain->length - $domain->decimals, $operands)) + $scale;

            return new Domain(Kind::Double, $result->field, $length, $scale, $result->unsigned, null, [], Coercibility::Numeric);
        }
        return $result;
    }

    /**
     * Reads a returned field independently of its original evaluation kind.
     */
    public function operand(Domain $domain, Derivation $derivation, bool $legacy): Domain
    {
        if ($domain->field === Field::LongLong && in_array($domain->kind, [Kind::Year, Kind::Bit], true)) {
            return Domain::integer(Field::LongLong, $domain->length, $domain->unsigned);
        }
        if ($domain->field !== Field::VarString || (!$domain->kind->temporal() && $domain->kind !== Kind::Year)) {
            return $domain;
        }

        return Domain::string($domain->length, $legacy ? $domain->collation : Settings::of($derivation->context)->connection, Field::VarString, $legacy ? Coercibility::Numeric : Coercibility::Coercible);
    }
}
