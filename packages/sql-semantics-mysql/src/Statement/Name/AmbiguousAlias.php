<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Snapshot;

/**
 * A name that no column of the position has and that several different select list items carry as their alias; none is chosen.
 *
 * MySQL reports such a name with ER_NON_UNIQ_ERROR ("Column '%s' in %s is
 * ambiguous"); items that compute the same expression under the same alias
 * do not make a name ambiguous. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/problems-with-alias.html.
 *
 * @visibility public
 * @example Keeping every select list item an alias names
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 AS x, 2 AS x ORDER BY x + 1', []);
 *     count($query->facts->diagnostics[0]->candidates) // => 2
 * @example Refusing a single candidate
 *     new \SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias(new \SqlSemantics\Statement\Identifier\Name('x'), []) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class AmbiguousAlias implements Resolution, Diagnostic
{
    use Snapshot;

    /**
     * @var list<Field> The select list items the alias names, in select list order
     */
    public readonly array $candidates;

    /**
     * @param Name $name The name used
     * @param list<Field> $candidates The select list items the alias names; at least two
     */
    public function __construct(public readonly Name $name, array $candidates)
    {
        $this->candidates = Check::listOf($candidates, Field::class, 'An ambiguous alias names at least two select list items.', 2);
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Column ' . $this->name->value . ' is ambiguous.';
    }
}
