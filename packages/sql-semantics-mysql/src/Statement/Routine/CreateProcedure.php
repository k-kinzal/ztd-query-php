<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * CREATE PROCEDURE: a stored procedure with its parameters, characteristics and body.
 *
 * The statement is structured as a request: it declares nothing to a
 * context. The facts follow MYSQL-ROUTINE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @example Rejecting RETURN outside a function declaration
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @example Rejecting a body that contradicts its language
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block(), [new \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage(new \SqlSemantics\Statement\Identifier\Name('JAVASCRIPT'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @example Rejecting a multi-valued index cast in a stored body
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Dml\Evaluation([new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Signed), true))])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @visibility public
 * @example Reading a procedure definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE shop.p(IN a INT) DETERMINISTIC SELECT a');
 *     [$create->statement->name->name->value, count($create->statement->parameters->parameters), $create->toString()] // => ['p', 1, 'CREATE PROCEDURE shop.p(IN a INT) DETERMINISTIC SELECT a']
 */
final class CreateProcedure implements Statement
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
     * @param QualifiedName $name The procedure name with its optional database
     * @param ParameterList $parameters The parameters
     * @param Node $body The body: a program statement, an SQL statement, or external code
     * @param list<Characteristic> $characteristics The characteristics in written order
     * @param Account|null $definer The DEFINER clause, when written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written (MySQL 8.0.29 and later)
     * @throws InvalidConstruction When the body or a characteristic is of another class
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly ParameterList $parameters,
        Node $body,
        array $characteristics = [],
        public readonly ?Account $definer = null,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($name->catalog === null, 'A routine name has at most a database qualifier.');
        $this->characteristics = Check::listOf($characteristics, Characteristic::class, 'A routine holds characteristics.');
        $this->body = (new ProgramFacts())->body($body);
        (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyLanguage())->check($this->body, $this->characteristics);
        (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyStructure())->check($this->body, ProgramKind::Procedure, $name);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the parameters and the body.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ProgramFacts())->routine($this->parameters, $this->body, ProgramKind::Procedure, $this->name, $derivation);
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
        $out->keyword('PROCEDURE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new ProgramNames())->qualified($out, $this->name);
        $out->glue()->node($this->parameters);
        foreach ($this->characteristics as $characteristic) {
            $out->node($characteristic);
        }
        $out->node($this->body);
    }
}
