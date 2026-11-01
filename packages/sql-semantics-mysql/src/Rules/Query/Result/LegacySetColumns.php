<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Result;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Settles legacy set fields in operand order, retaining column-reference facts.
 *
 * Black-box 5.6.51 and 5.7.44 observations distinguish a leading expression's
 * bound from later numeric storage bounds. A later computed DOUBLE contributes
 * 53 characters to a text result; a referenced DOUBLE contributes its field
 * width. FLOAT with a following BIGINT stays FLOAT, while the reverse order
 * promotes to DOUBLE. No input SQL or parser structure is needed.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LegacySetColumns
{
    /**
     * Applies the observed numeric and textual set allocation rules.
     *
     * @param list<Domain> $domains The interpreted operands, including NULL markers
     * @param list<\SqlSemantics\Statement\Shape\Field|null> $fields Original projection facts, when available
     */
    public function resolve(Domain $result, array $domains, array $fields): Domain
    {
        if ($domains === []) {
            return $result;
        }
        if ($result->kind === Kind::String) {
            return $this->text($result, $domains, $fields);
        }
        $present = array_values(array_filter($domains, static fn (Domain $domain): bool => $domain->kind !== Kind::Null));
        if ($present !== [] && array_filter($present, static fn (Domain $domain): bool => $domain->kind !== Kind::Integer) === []) {
            return $this->integer($domains, $fields);
        }
        if ($result->kind === Kind::Double) {
            return $this->floating($result, $domains);
        }
        if ($result->kind === Kind::Decimal) {
            return $this->decimal($result, $domains, $fields);
        }
        return $result;
    }

    /**
     * Combines integer fields in order, retaining the leading signedness.
     * Later MEDIUMINT fields contribute eight characters, even with a wider
     * display width. Following BIGINT they select INT; replacing NULL they
     * select BIGINT. These asymmetries are observed on both legacy releases.
     *
     * @param non-empty-list<Domain> $domains The integer or NULL operands
     * @param list<\SqlSemantics\Statement\Shape\Field|null> $fields Original projection facts
     */
    public function integer(array $domains, array $fields = []): Domain
    {
        $rank = [Field::Null, Field::Tiny, Field::Short, Field::Int24, Field::Long, Field::LongLong];
        $field = $domains[0]->field;
        $length = $this->first($domains[0], $fields[0] ?? null);
        foreach (array_slice($domains, 1) as $domain) {
            if ($domain->kind === Kind::Null) {
                continue;
            }
            $length = max($length, match ($domain->field) {
                Field::LongLong => $field === Field::Null ? 20 : max(20, $domain->length),
                Field::Int24 => 8,
                Field::Decimal, Field::Tiny, Field::Short, Field::Long, Field::Float, Field::Double, Field::Null, Field::Timestamp, Field::Date, Field::Time, Field::DateTime, Field::Year, Field::NewDate, Field::VarChar, Field::Bit, Field::Vector, Field::Json, Field::NewDecimal, Field::Enum, Field::Set, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::VarString, Field::String, Field::Geometry => $domain->length,
            });
            $field = match (true) {
                $domain->field === Field::Int24 && $field === Field::Null => Field::LongLong,
                $domain->field === Field::Int24 && $field === Field::LongLong => Field::Long,
                array_search($domain->field, $rank, true) > array_search($field, $rank, true) => $domain->field,
                default => $field,
            };
        }

        return Domain::integer($field, $length, $domains[0]->unsigned);
    }

    /**
     * Keeps a leading computed integer's reported bound. Column references
     * contribute their value width instead of a declared display width.
     */
    public function first(Domain $domain, ?\SqlSemantics\Statement\Shape\Field $field): int
    {
        $original = $field === null ? null : (new \SqlSemantics\Platform\MySql\Rules\Typing\Precision())->domain($field->type);
        return $domain->kind === Kind::Integer && $original !== null && $field->slot->column === null && $field->slot->origin === null ? $original->metadataLength() : $domain->length;
    }

    /**
     * Retains the first bound and applies later numeric field allocation.
     *
     * @param list<Domain> $domains The operand domains in statement order
     * @param list<\SqlSemantics\Statement\Shape\Field|null> $fields Original projection facts, when available
     */
    public function text(Domain $result, array $domains, array $fields): Domain
    {
        $length = 0;
        $decimals = 0;
        foreach ($domains as $index => $domain) {
            $slot = ($fields[$index] ?? null)?->slot;
            $reference = $slot !== null && ($slot->column !== null || $slot->origin !== null);
            $bound = match (true) {
                $index === 0 => $this->first($domain, $fields[0] ?? null),
                $domain->field === Field::LongLong => max(20, $domain->length),
                $domain->field === Field::Int24 => 8,
                $domain->field === Field::Double && !$reference => 53,
                default => $domain->length,
            };
            $length = max($length, $bound);
            $decimals = max($decimals, $domain->kind === Kind::String ? Domain::NOT_FIXED : $domain->decimals);
        }
        return new Domain($result->kind, $result->field, $length, $decimals, $domains[0]->unsigned, $result->collation, [], $result->coercibility);
    }

    /**
     * Keeps FLOAT only while every following field permits that storage type.
     *
     * @param list<Domain> $domains The original operand fields
     */
    public function floating(Domain $result, array $domains): Domain
    {
        $present = array_values(array_filter($domains, static fn (Domain $domain): bool => $domain->kind !== Kind::Null));
        if ($domains === [] || $present === []) {
            return $result;
        }
        $single = true;
        $floated = false;
        foreach ($present as $domain) {
            $single = $single && (in_array($domain->field, [Field::Float, Field::Tiny, Field::Short, Field::Int24], true) || ($floated && $domain->field === Field::LongLong));
            $floated = $floated || $domain->field === Field::Float;
        }
        $scale = max(array_column($domains, 'decimals'));
        $same = count(array_filter($domains, static fn (Domain $domain): bool => $domain->field === $domains[0]->field && $domain->length === $domains[0]->length && $domain->decimals === $domains[0]->decimals)) === count($domains);
        $length = max(array_map(static fn (Domain $domain): int => $domain->length - $domain->decimals, $domains)) + $scale;
        $fixed = $scale < Domain::NOT_FIXED && ($single ? ($same || $length <= 8) : ($scale <= 17 && ($same || $length <= 19)));
        return new Domain(Kind::Double, $single ? Field::Float : Field::Double, $fixed ? $length : ($single ? 12 : 22), $fixed ? $scale : Domain::NOT_FIXED, $domains[0]->unsigned, null, [], Coercibility::Numeric);
    }
    /**
     * Retains literal digit counts separately from column sign widths.
     *
     * @param list<Domain> $domains The operand domains
     * @param list<\SqlSemantics\Statement\Shape\Field|null> $fields Original projection facts
     */
    public function decimal(Domain $result, array $domains, array $fields): Domain
    {
        $length = $result->length;
        foreach ($domains as $index => $domain) {
            $expression = ($fields[$index] ?? null)?->expression;
            while (($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast && in_array($expression->target->kind, [\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Signed, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Unsigned], true)) || $expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped || ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary && $expression->operator === \SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Plus)) {
                $expression = $expression->operand;
            }
            if ($domain->kind === Kind::Integer && $expression instanceof \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral) {
                $length = max($length, $domain->length + $result->decimals + ($result->decimals > 0 ? 1 : 0) + 1);
            }
            if ($index > 0 && $domain->field === Field::LongLong) {
                $length = max(20, $length);
            }
        }
        return new Domain($result->kind, $result->field, $length, $result->decimals, $result->unsigned, $result->collation, [], $result->coercibility);
    }

}
