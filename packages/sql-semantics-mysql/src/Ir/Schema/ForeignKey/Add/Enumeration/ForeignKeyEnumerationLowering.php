<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration;

use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalSet;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;

/**
 * Settles native labels and their ordered numeric representation from an external field.
 * @visibility SqlSemantics
 */
final class ForeignKeyEnumerationLowering
{
    /**
     * Reads an ENUM ordinal or SET mask field with a resolved label collation.
     */
    public function storage(Column $column, GrammarRelease $release): ?ForeignKeyEnumerationStorage
    {
        $type = $column->type;
        if (!$type instanceof Enumeration) {
            return null;
        }
        $collation = (new ForeignKeyStringLowering())->collation($column, $type->charset);
        Check::input($collation !== null, 'A native ENUM or SET field needs a resolved utf8mb4 collation.');
        $kind = match ($type->kind) {
            EnumerationKind::Enum => ForeignKeyEnumerationKind::Enum,
            EnumerationKind::Set => ForeignKeyEnumerationKind::Set,
        };
        $legacy = (new ForeignKeyRemovalSet())->legacy($release);
        $members = $distinct = [];
        foreach ($type->members as $label) {
            Check::input($label->radix === null, 'A native ENUM or SET member needs a decoded character label.');
            if ($kind === ForeignKeyEnumerationKind::Set) {
                Check::input(!str_contains($label->value, ','), 'A native SET member cannot contain a comma.');
                $distinct[':' . rtrim($label->value, ' ')] = true;
            }
            $members[] = new ForeignKeyEnumerationMember($this->normalized($label->value, $legacy));
        }
        Check::input(count($distinct) <= 64, 'A native SET requires a checked upper bound of 64 distinct members.');

        return new ForeignKeyEnumerationStorage($kind, $members, $collation);
    }

    /**
     * Applies native trailing-space normalization and legacy label termination.
     */
    public function normalized(string $label, bool $legacy): string
    {
        $normalized = rtrim($label, ' ');
        $terminator = $legacy ? strpos($normalized, "\0") : false;

        return $terminator === false ? $normalized : substr($normalized, 0, $terminator);
    }
}
