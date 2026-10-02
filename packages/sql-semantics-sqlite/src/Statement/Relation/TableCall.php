<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;

/**
 * One occurrence of a table-valued function as query input: a name with arguments.
 *
 * Rule: SQLITE-TABLE-CALL-001. The arguments are derived where the relations
 * to the left of the call are visible. A name that is a declared relation or
 * a common table has the shape of that relation; any other name is a
 * table-valued function, whose columns come from a virtual table module the
 * context does not declare, so the shape is open and names the undeclared
 * routine.
 * Source: https://sqlite.org/vtab.html#table_valued_functions. Status: Implemented.
 *
 * @visibility public
 * @example Reading a table-valued function call
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("SELECT * FROM json_each('[1]') AS j", []);
 *     [$query->statement->from->name->name->value, count($query->statement->from->arguments), $query->shape()->missing[0]->describe()] // => ['json_each', 1, 'the signature of routine json_each']
 */
final class TableCall implements Relation
{
    use Snapshot;

    /**
     * @var list<Scalar> The arguments in order
     */
    public readonly array $arguments;

    /**
     * @param QualifiedName $name The function name with its optional schema
     * @param list<Scalar> $arguments The arguments in order
     * @param Name|null $alias The correlation name
     */
    public function __construct(public readonly QualifiedName $name, array $arguments, public readonly ?Name $alias = null)
    {
        $this->arguments = Check::listOf($arguments, Scalar::class, 'The arguments of a table-valued function are expressions.');
    }

    /**
     * Derives the arguments and the shape of the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        foreach ($this->arguments as $argument) {
            $derivation->scalar($argument, $environment);
        }
        $resolution = $derivation->table($this->name, $environment);
        if ($resolution instanceof DeclaredTable || $resolution instanceof CommonTable) {
            return (new TableShapes())->fact($derivation, $this->name, $environment);
        }

        return new RelationFact(new RowShape([], [new UndeclaredRoutine($this->name)]));
    }

    /**
     * Writes the call and the correlation name.
     */
    public function render(Output $out): void
    {
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Routine)->symbol('(')->list($this->arguments)->symbol(')');
        if ($this->alias !== null) {
            $out->keyword('AS')->name($this->alias, NameUse::Alias);
        }
    }
}
