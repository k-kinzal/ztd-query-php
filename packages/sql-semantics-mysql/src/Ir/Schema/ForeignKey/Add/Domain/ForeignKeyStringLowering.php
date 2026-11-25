<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;

/**
 * Resolves full native string fields from their checked declarations.
 * @visibility SqlSemantics
 */
final class ForeignKeyStringLowering
{
    /**
     * Produces a byte-string or explicitly collated character storage value.
     */
    public function storage(Column $column): ?ForeignKeyStringStorage
    {
        $type = $column->type;
        if ($type instanceof Binary) {
            $kind = match ($type->kind) {
                BinaryKind::Binary => ForeignKeyStringKind::Binary,
                BinaryKind::VarBinary => ForeignKeyStringKind::Varbinary,
                BinaryKind::TinyBlob, BinaryKind::Blob, BinaryKind::MediumBlob, BinaryKind::LongBlob, BinaryKind::LongVarBinary => null,
            };
            return $kind === null ? null : new ForeignKeyStringStorage($kind, $this->length($type->length, $kind));
        }
        if (!$type instanceof Character || $type->national) {
            return null;
        }
        $kind = match ($type->kind) {
            CharacterKind::Char => ForeignKeyStringKind::Char,
            CharacterKind::VarChar, CharacterKind::CharVarying => ForeignKeyStringKind::Varchar,
            CharacterKind::TinyText, CharacterKind::Text, CharacterKind::MediumText, CharacterKind::LongText,
            CharacterKind::Long, CharacterKind::LongVarChar, CharacterKind::LongCharVarying => null,
        };
        $collation = ForeignKeyTextCollation::tryFrom(strtolower($column->collation->value ?? ''));
        $charset = $type->charset;
        if ($kind === null || $collation === null
            || ($charset !== null && ($charset->binary() || strtolower($charset->charset->value ?? '') !== 'utf8mb4'))) {
            return null;
        }

        return new ForeignKeyStringStorage($kind, $this->length($type->length, $kind), $collation);
    }

    /**
     * Reads the full declared width and the native fixed-width default.
     */
    public function length(?string $written, ForeignKeyStringKind $kind): int
    {
        if ($written === null && $kind->fixed()) {
            return 1;
        }
        Check::input($written !== null && preg_match('/\A[0-9]+\z/D', $written) === 1, 'A native string key requires a checked declared length.');

        return (int) $written;
    }
}
