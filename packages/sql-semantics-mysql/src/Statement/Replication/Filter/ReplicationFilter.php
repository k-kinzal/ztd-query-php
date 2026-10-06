<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Filter;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One `filter = (value, …)` of CHANGE REPLICATION FILTER; an empty list clears the filter.
 *
 * The values are of the class the filter lists (FilterKind::member): a
 * table name is always qualified by its database. The names are filter
 * rules, not references: they are not resolved against declarations.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-filter.html.
 *
 * @visibility public
 * @example Holding the databases of a filter
 *     $filter = new \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter(\SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind::DoDb, [new \SqlSemantics\Statement\Identifier\Name('a')]);
 *     $filter->values[0]->value // => 'a'
 */
final class ReplicationFilter implements Node
{
    use Snapshot;

    /**
     * @var list<Name|QualifiedName|Text|DatabaseRewrite> The values in written order
     */
    public readonly array $values;

    /**
     * @param FilterKind $kind The filter
     * @param list<Name|QualifiedName|Text|DatabaseRewrite> $values The values in written order, of the class the filter lists
     */
    public function __construct(public readonly FilterKind $kind, array $values)
    {
        $this->values = Check::listOf($values, $kind->member(), 'The values of ' . $kind->value . ' are of the class the filter lists.');
        foreach ($this->values as $value) {
            Check::input(!$value instanceof QualifiedName || ($value->schema !== null && $value->catalog === null), 'A filtered table is qualified by its database.');
        }
    }

    /**
     * Writes the filter, `=` and the parenthesized values.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->symbol('=')->symbol('(');
        foreach ($this->values as $position => $value) {
            if ($position > 0) {
                $out->symbol(',');
            }
            if ($value instanceof Name) {
                $out->name($value, NameUse::Qualifier);
            } elseif ($value instanceof QualifiedName) {
                $out->name($value->schema ?? $value->name, NameUse::Qualifier)->symbol('.')->name($value->name, NameUse::Relation);
            } else {
                $out->node($value);
            }
        }
        $out->symbol(')');
    }
}
