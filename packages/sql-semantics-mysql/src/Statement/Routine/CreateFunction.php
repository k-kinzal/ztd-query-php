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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\FunctionBody;
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
 * @example Rejecting a body with no RETURN instruction
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('f')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int), new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block())) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @example Rejecting a body that contradicts its language
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('f')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int), new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('code')))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @example Rejecting a client marker without a binding scope
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('f')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int), new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter()))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @example Rejecting a multi-valued index cast in a stored body
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Boolean), new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Signed), true)))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @example Rejecting an intrinsically incomplete character return collation
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Type\Character(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Char, '1', charset: new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Binary));
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('f')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), $type, new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
     * @var ExternalBody|FunctionBody The external code or SQL instruction structure containing RETURN
     */
    public readonly ExternalBody|FunctionBody $body;

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
    ) {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        ProgramNames::check($name);
        Check::input($name->catalog === null, 'A routine name has at most a database qualifier.');
        (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\DeclaredCollation())->check($returns, $collation);
        Check::input(!(new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\ReturnCollation())->intrinsic($returns), 'A character function return requires a resolved collation rather than BINARY alone.');
        foreach ($parameters->parameters as $parameter) {
            Check::input($parameter->mode === null, 'A function parameter has no direction.');
        }
        $this->characteristics = $implementation->characteristics;
        $this->body = $implementation->body;
        (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyStructure())->check($this->body, ProgramKind::Function, $name, $parameters);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the parameters and the body, and checks that the body returns.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ProgramFacts())->routine($this->parameters, $this->body, ProgramKind::Function, $this->name, $derivation, $this->returns, $this->collation);
    }

    /**
     * Tells whether defining this function produces the native-name collision note.
     * Both keyword names and names resolved by the native function catalog collide.
     * Qualification permits the definition without removing that name relationship.
     *
     * @example Reading the name relationship of a function definition
     *     $definition = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE FUNCTION d.`ORDER`() RETURNS INT RETURN 1')->statement;
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
