<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * The part a CREATE VIEW and an ALTER VIEW share: algorithm, definer, security, name, column list, query and check option.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-view.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-view.html.
 *
 * @visibility public
 * @example Reading a view definition
 *     $view = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE ALGORITHM = MERGE SQL SECURITY INVOKER VIEW v (x) AS SELECT 1 WITH LOCAL CHECK OPTION');
 *     [$view->statement->definition->algorithm, $view->statement->definition->security, $view->statement->definition->check] // => [\SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm::Merge, \SqlSemantics\Platform\MySql\Statement\View\ViewSecurity::Invoker, \SqlSemantics\Platform\MySql\Statement\View\ViewCheckOption::Local]
 */
final class ViewDefinition implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Name>|null The column list, or null when none is written
     */
    public readonly ?array $columns;

    /**
     * @param QualifiedName $name The view name
     * @param Query $query The query the view stands for
     * @param list<Name>|null $columns The column list; null when none is written, otherwise at least one
     * @param ViewAlgorithm|null $algorithm The ALGORITHM clause, when written
     * @param Account|null $definer The DEFINER clause, when written
     * @param ViewSecurity|null $security The SQL SECURITY clause, when written
     * @param ViewCheckOption|null $check The WITH CHECK OPTION clause, when written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written (MySQL 9.1 and later)
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly Query $query,
        ?array $columns = null,
        public readonly ?ViewAlgorithm $algorithm = null,
        public readonly ?Account $definer = null,
        public readonly ?ViewSecurity $security = null,
        public readonly ?ViewCheckOption $check = null,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($name->catalog === null, 'A view name has at most a database qualifier.');
        $this->columns = $columns === null ? null : Check::listOf($columns, Name::class, 'A written column list names at least one column.', 1);
    }

    /**
     * Writes the clauses before VIEW: the algorithm, the definer and the security.
     */
    public function renderHead(Output $out): void
    {
        if ($this->algorithm !== null) {
            $out->keyword('ALGORITHM')->symbol('=')->keyword($this->algorithm->value);
        }
        if ($this->definer !== null) {
            $out->keyword('DEFINER')->symbol('=')->node($this->definer);
        }
        if ($this->security !== null) {
            $out->keyword('SQL', 'SECURITY', $this->security->value);
        }
    }

    /**
     * Writes the clauses after VIEW: IF NOT EXISTS, the name, the column list, the query and the check option.
     */
    public function render(Output $out): void
    {
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Relation);
        if ($this->columns !== null) {
            $out->symbol('(');
            foreach ($this->columns as $position => $column) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($column, NameUse::Column);
            }
            $out->symbol(')');
        }
        $out->keyword('AS')->node($this->query);
        if ($this->check !== null) {
            $out->keyword('WITH');
            if ($this->check !== ViewCheckOption::Unqualified) {
                $out->keyword($this->check->value);
            }
            $out->keyword('CHECK', 'OPTION');
        }
    }
}
