<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\Resolution;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A name that no column of the position has and that several different select list items carry as their alias; none is chosen.
 *
 * MySQL reports such a name with ER_NON_UNIQ_ERROR ("Column '%s' in %s is
 * ambiguous"); items that compute the same expression under the same alias
 * do not make a name ambiguous. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/problems-with-alias.html.
 *
 * @visibility SqlSemantics
 * @example Refusing a single candidate
 *     new \SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('x'), []) // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction
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
