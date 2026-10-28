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
use SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * CREATE FUNCTION of a stored function: its parameters, return type, characteristics and body.
 *
 * The statement is structured as a request: it declares nothing to a
 * context. The facts follow MYSQL-ROUTINE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @example Retaining a character return whose collation prevents definition
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Type\Character(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Char, '1', charset: new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Binary));
 *     $function = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('f')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), $type, new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))));
 *     $function->returns === $type // => true
 *
 * @visibility public
 * @example Reading a function definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE FUNCTION twice(a INT) RETURNS BIGINT DETERMINISTIC RETURN a * 2');
 *     [$create->returns->name(), $create->diagnostics()] // => ['BIGINT', []]
 */
final class CreateFunction extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<Characteristic> The characteristics in written order
     */
    public readonly array $characteristics;

    /**
     * @var ExternalBody|ProgramStatement|Statement The external code or retained SQL instruction structure
     */
    public readonly ExternalBody|ProgramStatement|Statement $body;

    /**
     * @param QualifiedName $name The function name with its optional database
     * @param ParameterList $parameters The parameters; none has a direction
     * @param DeclaredName $returns The return type
     * @param FunctionImplementation $implementation The body and its interpreting characteristics
     * @param CollationName|null $collation The COLLATE clause; a named override requires a character set carried by the return type, and known names must be compatible
     * @param Account|null $definer The DEFINER clause, when written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written (MySQL 8.0.29 and later)
     * @throws InvalidConstruction When the name, parameter directions or combined declaration domains are inconsistent
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly ParameterList $parameters,
        public readonly DeclaredName $returns,
        FunctionImplementation $implementation,
        public readonly ?CollationName $collation = null,
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
        (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\DeclaredCollation())->check($returns, $collation);
        foreach ($parameters->parameters as $parameter) {
            Check::input($parameter->mode === null, 'A function parameter has no direction.');
        }
        $this->characteristics = $implementation->characteristics;
        $this->body = $implementation->body;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the parameters and the body, and checks that the body returns.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ProgramFacts())->routine($this->parameters, $this->body, ProgramKind::Function, $this->name, $derivation, $this->returns, $this->collation);
        (new \SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyConditions())->publish($this, $derivation);
    }

    /**
     * Tells whether defining this function produces the native-name collision note.
     * Both keyword names and names resolved by the native function catalog collide.
     * Qualification permits the definition without removing that name relationship.
     *
     * @example Reading the name relationship of a function definition
     *     $definition = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE FUNCTION d.`ORDER`() RETURNS INT RETURN 1');
     *     $definition->nameCollision(\SqlSemantics\Contract\GrammarRelease::MySql847) // => true
     */
    public function nameCollision(\SqlSemantics\Contract\GrammarRelease $release): bool
    {
        return (new \SqlSemantics\Platform\MySql\Rendering\Keywords($release))->contains($this->name->name->value)
            || (new \SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions())->exists($release, $this->name->name->value);
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
