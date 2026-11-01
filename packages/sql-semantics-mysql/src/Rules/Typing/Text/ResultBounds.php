<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Text;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Separates a computed string's expression bound from its reported blob width.
 * Control functions and string extrema retain the character bound for subsequent
 * CONCAT and HEX calls. Legacy expressions select their field from that bound;
 * modern expressions retain the selected argument's field. Verified through SQL
 * on MySQL 5.6 and 8.4 with physical, computed and nested string operands.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ResultBounds
{
    /**
     * Records an existing computed blob's byte width without treating it as characters.
     */
    public function computed(Domain $domain): Domain
    {
        if ($domain->kind !== Kind::String || !$domain->field->blob() || $domain->display !== null) {
            return $domain;
        }

        return new Domain(Kind::String, $domain->field, intdiv($domain->length, $domain->collation->charset->maxLength), $domain->decimals, false, $domain->collation, $domain->members, $domain->coercibility, $domain->length);
    }

    /**
     * Resolves a selected string operand, distinguishing a physical column from a computed result.
     */
    public function selected(Domain $domain, bool $legacy, bool $column): Domain
    {
        if ($domain->kind !== Kind::String) {
            return $domain;
        }
        $base = $domain->field === Field::Blob && $domain->display === null
            ? new Domain(Kind::String, $domain->field, $domain->length, $domain->decimals, false, $domain->collation, $domain->members, $domain->coercibility, min(4294967295, $domain->byteLength()))
            : $this->computed($domain);
        $bytes = min(4294967295, $base->byteLength());
        $field = $legacy ? ($bytes <= 65535 ? Field::VarString : ($bytes <= 16777215 ? Field::MediumBlob : Field::LongBlob)) : $base->field;

        return new Domain(Kind::String, $field, $base->length, $column ? Domain::NOT_FIXED : $base->decimals, false, $base->collation, $base->members, $base->coercibility, $field->blob() ? ($legacy ? $bytes : $base->display) : null);
    }
}
