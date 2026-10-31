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

/**
 * CREATE PROCEDURE: a stored procedure with its parameters, characteristics and body.
 *
 * The statement is structured as a request: it declares nothing to a
 * context. The facts follow MYSQL-ROUTINE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @example Refusing an empty procedure name before derivation
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block([], [])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a body that the server refuses in a procedure
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() RETURN 1') // throws \SqlSemantics\Construction\RejectedInput
 * @visibility public
 * @example Reading a procedure definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE shop.p(IN a INT) DETERMINISTIC SELECT a');
 *     [$create->name->name->value, count($create->parameters->parameters), $create->toString()] // => ['p', 1, 'CREATE PROCEDURE shop.p(IN a INT) DETERMINISTIC SELECT a']
 */
final class CreateProcedure extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

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
     * @throws InvalidConstruction When the body or a characteristic is of another class, or the body breaks a stored program restriction
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly ParameterList $parameters,
        Node $body,
        array $characteristics = [],
        public readonly ?Account $definer = null,
        public readonly bool $ifNotExists = false,
        ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        public readonly ?Definition\DefinitionSource $definitionSource = null,
    ) {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        ProgramNames::check($name);
        Check::input($name->catalog === null, 'A routine name has at most a database qualifier.');
        $this->characteristics = Check::listOf($characteristics, Characteristic::class, 'A routine holds characteristics.');
        $this->body = (new ProgramFacts())->body($body);
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyConditions())->refusal($this->body, ProgramKind::Procedure, $name, $parameters, $this->characteristics, $definitionSource?->bodyRefusalOffset) === null, 'A procedure body satisfies the stored program restrictions.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the parameters and the body.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->derive($derivation, [$this->definer]);
        (new ProgramFacts())->routine($this->parameters, $this->body, ProgramKind::Procedure, $this->name, $derivation);
        (new \SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyConditions())->order($this, $derivation);
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
