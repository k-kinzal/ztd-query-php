<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Catalog;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Identifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;

/**
 * Checks the parts of CREATE TYPE and ALTER TYPE that the server validates without a catalog lookup.
 *
 * Rule: PG-TYPE-CHECK-001. An enum label holds at most 63 bytes. The
 * attributes of a composite type have distinct names. The attributes of a
 * range type are PG-DEFINE-CHECK-001.
 * Termination: one pass over each finite list.
 * Source: https://www.postgresql.org/docs/17/datatype-enum.html#DATATYPE-ENUM-IMPLEMENTATION-DETAILS,
 * https://www.postgresql.org/docs/17/sql-createtype.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class TypeChecks
{
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
