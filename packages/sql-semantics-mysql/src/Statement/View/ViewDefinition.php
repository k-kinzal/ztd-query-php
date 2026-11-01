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

/**
 * The part a CREATE VIEW and an ALTER VIEW share: algorithm, definer, security, name, column list, query and check option.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-view.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-view.html.
 *
 * @example Retaining an intrinsically empty output as an execution condition
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])]);
 *     (new \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v')), $query))->hasExecutionCondition() // => true
 *
 * @example Retaining a qualified projection whose relation must be resolved
 *     $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'));
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Name\TableWildcard($name)]);
 *     (new \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition($name, $query))->hasExecutionCondition() // => true
 *
 * @visibility public
 * @example Reading a view definition
 *     $view = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE ALGORITHM = MERGE SQL SECURITY INVOKER VIEW v (x) AS SELECT 1 WITH LOCAL CHECK OPTION');
 *     [$view->definition->algorithm, $view->definition->security, $view->definition->check] // => [\SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm::Merge, \SqlSemantics\Platform\MySql\Statement\View\ViewSecurity::Invoker, \SqlSemantics\Platform\MySql\Statement\View\ViewCheckOption::Local]
 */
final class ViewDefinition implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

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
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
    ) {
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($name);
        Check::input($name->catalog === null, 'A view name has at most a database qualifier.');
        $this->columns = $columns === null ? null : Check::listOf($columns, Name::class, 'A written column list names at least one column.', 1);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ViewQueries())->check($query);
    }

    /**
     * Retains fixed read-column conditions for execution, alongside catalog dependencies.
     */
    public function hasExecutionCondition(): bool
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($this->query) !== null;
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
