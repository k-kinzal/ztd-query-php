<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * The UNION option of a MERGE table: the tables it merges, possibly none.
 *
 * The optional assignment sign is retained independently of the option value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) UNION = (t1, t2)');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\UnionOption // => true
 */
final class UnionOption implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<QualifiedName> The merged tables in order
     */
    public readonly array $tables;

    /**
     * @param list<QualifiedName> $tables The merged tables in order
     * @param \SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null $operator The written assignment sign, or null when omitted
     */
    public function __construct(array $tables, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly ?\SqlSemantics\Platform\MySql\Statement\AssignmentOperator $operator = null)
    {
        $this->tables = Check::listOf($tables, QualifiedName::class, 'The merged tables are an ordered list of table names.');
        foreach ($this->tables as $table) {
            (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($table);
            Check::input($table->catalog === null, 'A table name has at most a database qualifier.');
        }
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('UNION');
        if ($this->operator !== null) {
            $out->symbol($this->operator->value);
        }
        $out->symbol('(');
        foreach ($this->tables as $position => $table) {
            if ($position > 0) {
                $out->symbol(',');
            }
            if ($table->schema !== null) {
                $out->name($table->schema, NameUse::Qualifier)->symbol('.');
            }
            $out->name($table->name, NameUse::Relation);
        }
        $out->symbol(')');
    }
}
