<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A FORCE option of the old COPY syntax: FORCE QUOTE, FORCE NOT NULL or FORCE NULL with a column list or `*`.
 *
 * `*` stands for every column copied (FORCE NOT NULL * and FORCE NULL *
 * from PostgreSQL 17).
 * Source: https://www.postgresql.org/docs/17/sql-copy.html#id-1.9.3.55.10.
 *
 * @visibility SqlSemantics
 * @example Refusing columns together with the star
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyForce(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyForceKind::Quote, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a')], true) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class CopyForce implements Node
{
    use Snapshot;

    /**
     * @var list<Name> The columns named; empty with the star
     */
    public readonly array $columns;

    /**
     * @param CopyForceKind $kind The option set
     * @param list<Name> $columns The columns named; empty with the star
     * @param bool $all Whether `*` is written instead of columns
     *
     * @throws InvalidConstruction When both or neither of columns and the star are written
     */
    public function __construct(public readonly CopyForceKind $kind, array $columns, public readonly bool $all = false)
    {
        $this->columns = Check::listOf($columns, Name::class, 'FORCE names columns.');
        Check::input($all === ($this->columns === []), 'FORCE names columns or writes *.');
    }

    /**
     * Answers the name of the generic option the item sets.
     */
    public function option(): string
    {
        return match ($this->kind) {
            CopyForceKind::Quote => 'force_quote',
            CopyForceKind::NotNull => 'force_not_null',
            CopyForceKind::Null => 'force_null',
        };
    }

    /**
     * Writes FORCE, the option and the columns or the star.
     */
    public function render(Output $out): void
    {
        $out->keyword(...match ($this->kind) {
            CopyForceKind::Quote => ['FORCE', 'QUOTE'],
            CopyForceKind::NotNull => ['FORCE', 'NOT', 'NULL'],
            CopyForceKind::Null => ['FORCE', 'NULL'],
        });
        if ($this->all) {
            $out->symbol('*');

            return;
        }
        foreach ($this->columns as $position => $column) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($column, NameUse::Column);
        }
    }
}
