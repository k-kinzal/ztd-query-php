<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Catalog;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Identifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;

/**
 * Checks the parts of CREATE TYPE and ALTER TYPE that the server validates without a catalog lookup.
 *
 * Rule: PG-TYPE-CHECK-001. An enum label holds at most 63 bytes. A range
 * type recognizes the attributes subtype, subtype_opclass, collation,
 * canonical, subtype_diff and multirange_type_name, each once, and requires
 * subtype. The attributes of a composite type have distinct names.
 * Termination: one pass over each finite list.
 * Source: https://www.postgresql.org/docs/17/datatype-enum.html#DATATYPE-ENUM-IMPLEMENTATION-DETAILS,
 * https://www.postgresql.org/docs/17/sql-createtype.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class TypeChecks
{
    /**
     * The attributes of CREATE TYPE ... AS RANGE.
     */
    private const RANGE = ['subtype', 'subtype_opclass', 'collation', 'canonical', 'subtype_diff', 'multirange_type_name'];

    /**
     * Reports each enum label longer than 63 bytes.
     *
     * @param list<StringConstant> $labels
     */
    public function labels(Derivation $derivation, array $labels): void
    {
        foreach ($labels as $label) {
            if (strlen($label->value) > Identifiers::LIMIT) {
                $derivation->report(new CatalogMisuse(CatalogMisuseRule::EnumLabelLength, [$label->value]));
            }
        }
    }

    /**
     * Checks the attributes of a range type.
     *
     * @param list<Definition> $options
     */
    public function range(Derivation $derivation, array $options): void
    {
        $names = [];
        foreach ($options as $option) {
            $names[] = $option->name->value;
            if (!in_array($option->name->value, self::RANGE, true)) {
                $derivation->report(new CatalogMisuse(CatalogMisuseRule::RangeAttribute, [$option->name->value]));
            }
        }
        (new OptionChecks())->redundant($derivation, $names);
        if (!in_array('subtype', $names, true)) {
            $derivation->report(new CatalogMisuse(CatalogMisuseRule::RangeSubtype));
        }
    }

    /**
     * Reports each attribute name given more than once.
     *
     * @param list<TypedColumn> $attributes
     */
    public function attributes(Derivation $derivation, array $attributes): void
    {
        $seen = [];
        foreach ($attributes as $attribute) {
            $name = $attribute->name->value;
            if (isset($seen[$name]) && $seen[$name] === 1) {
                $derivation->report(new CatalogMisuse(CatalogMisuseRule::AttributeTwice, [$name]));
            }
            $seen[$name] = ($seen[$name] ?? 0) + 1;
        }
    }
}
