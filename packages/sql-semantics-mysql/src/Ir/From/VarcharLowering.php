<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;

/**
 * Resolves the supported explicit collation and native character length of a declared field.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class VarcharLowering
{
    /**
     * Reads the collation and the character length from a resolved declaration.
     */
    public function lower(Column $column): Varchar
    {
        $type = $column->type;
        Check::input(
            $type instanceof Character && ($type->kind === CharacterKind::VarChar || $type->kind === CharacterKind::CharVarying)
                && !$column->generated && !$type->national && $type->length !== null && preg_match('/\A[0-9]+\z/', $type->length) === 1,
            'The text slot requires an ordinary native VARCHAR with a character length.',
        );
        $collation = TextCollation::tryFrom(strtolower($column->collation->value ?? ''));
        Check::input($collation !== null, 'The text slot requires an explicit utf8mb4_bin collation.');
        $charset = $type->charset;
        Check::input(
            $charset === null || (!$charset->binary() && strtolower($charset->charset->value ?? '') === 'utf8mb4'),
            'The explicit VARCHAR collation requires the matching utf8mb4 character set.',
        );

        Check::input($column->nullability !== Nullability::Dependent, 'A native VARCHAR slot requires known column nullability.');

        return new Varchar((int) $type->length, $collation, $column->nullability === Nullability::Nullable);
    }
}
