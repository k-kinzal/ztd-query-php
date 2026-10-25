<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name;

use InvalidArgumentException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Generates valid declaration names without requiring an existing database state.
 * Names contain 1–64 characters, including quotes, spaces and Unicode, without a trailing space.
 */
final class ColumnNameGenerator implements Inhabitant
{
    /**
     * MySQL 5.x additionally accepts table qualifiers and a leading dot in column definitions.
     */
    public function __construct(private readonly GrammarRelease $release)
    {
    }

    /**
     * Constructs a ColumnName directly, preserving the selected spelling form.
     *
     * @throws InvalidArgumentException When the selected grammar is not MySQL
     */
    public function sample(Choices $choices): ColumnName
    {
        $length = $choices->integer(1, 64);
        $characters = array_map(static fn (): string => $choices->one(['a', 'Z', '0', '_', '`', ' ', '.', 'é', '列']), range(1, $length));
        $characters[$length - 1] = $choices->one(['a', 'Z', '0', '_', '`', '.', 'é', '列']);
        $column = new Name(implode('', $characters));
        $form = match ($this->release) {
            GrammarRelease::MySql5651, GrammarRelease::MySql5744 => $choices->integer(0, 3),
            GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820,
            GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901,
            GrammarRelease::MySql910 => 0,
            GrammarRelease::PostgreSql166, GrammarRelease::PostgreSql172, GrammarRelease::Sqlite3472 => throw new InvalidArgumentException('ColumnName generation requires a MySQL grammar.'),
        };

        return match ($form) {
            1 => new ColumnName($column, dot: OptionalWords::Written),
            2 => new ColumnName($column, new QualifiedName(new Name('table_' . $choices->integer(0, 100000)))),
            3 => new ColumnName($column, new QualifiedName(new Name('table_' . $choices->integer(0, 100000)), new Name('schema_' . $choices->integer(0, 100000)))),
            default => new ColumnName($column),
        };
    }
}
