<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * CREATE FUNCTION of a stored function: its parameters, return type, characteristics and body.
 *
 * The statement is structured as a request: it declares nothing to a
 * context. The facts follow MYSQL-ROUTINE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility public
 * @example Reading a function definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE FUNCTION twice(a INT) RETURNS BIGINT DETERMINISTIC RETURN a * 2');
 *     [$create->statement->returns->name(), $create->facts->diagnostics] // => ['BIGINT', []]
 */
final class CreateFunction implements Statement
{
    use Snapshot;

    /**
     * @var list<Characteristic> The characteristics in written order
     */
    public readonly array $characteristics;

    /**
     * @var ExternalBody|ProgramStatement|Statement The routine body
     */
    public readonly ExternalBody|ProgramStatement|Statement $body;

    /**
     * @param QualifiedName $name The function name with its optional database
     * @param ParameterList $parameters The parameters; none has a direction
     * @param TypeName $returns The return type
     * @param Node $body The body: a program statement, an SQL statement, or external code
     * @param CollationName|null $collation The COLLATE clause of the return type
     * @param list<Characteristic> $characteristics The characteristics in written order
     * @param Account|null $definer The DEFINER clause, when written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written (MySQL 8.0.29 and later)
     * @throws InvalidConstruction When a parameter has a direction, or the body or a characteristic is of another class
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly ParameterList $parameters,
        public readonly TypeName $returns,
        Node $body,
        public readonly ?CollationName $collation = null,
        array $characteristics = [],
        public readonly ?Account $definer = null,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($name->catalog === null, 'A routine name has at most a database qualifier.');
        foreach ($parameters->parameters as $parameter) {
            Check::input($parameter->mode === null, 'A function parameter has no direction.');
        }
        $this->characteristics = Check::listOf($characteristics, Characteristic::class, 'A routine holds characteristics.');
        $this->body = (new ProgramFacts())->body($body);
    }

    /**
     * Derives the parameters and the body, and checks that the body returns.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ProgramFacts())->routine($this->parameters, $this->body, ProgramKind::Function, $this->name, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->definer !== null) {
            $out->keyword('DEFINER')->symbol('=')->node($this->definer);
        }
        $out->keyword('FUNCTION');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new ProgramNames())->qualified($out, $this->name);
        $out->glue()->node($this->parameters)->keyword('RETURNS');
        (new ProgramNames())->type($out, $this->returns, $this->collation);
        foreach ($this->characteristics as $characteristic) {
            $out->node($characteristic);
        }
        $out->node($this->body);
    }
}
