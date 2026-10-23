<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Definition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyLanguage;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyStructure;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\FunctionBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A function's instruction body and the characteristics that interpret it.
 *
 * The effective LANGUAGE agrees with the body representation. SQL instructions
 * contain RETURN and obey the restrictions of a stored function. These local
 * invariants are independent of the declaration's name, parameters and result
 * type, so this value can be constructed before those other operands. It is a
 * declaration operand, not an instruction that can be placed in another body.
 * CreateFunction checks assignments whose interpretation depends on its parameter
 * names after the body and parameter list have been combined.
 *
 * @visibility public
 * @example Refusing an SQL body interpreted as an external language
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')), [new \SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage(new \SqlSemantics\Statement\Identifier\Name('javascript'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Constructing a SQL implementation independently of a function name
 *     $implementation = new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')));
 *     count($implementation->characteristics) // => 0
 * @example Excluding values that cannot contain a RETURN instruction
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()) // throws \TypeError
 */
final class FunctionImplementation
{
    use Snapshot;

    /**
     * @var ExternalBody|FunctionBody The language-specific body with its structural restrictions
     */
    public readonly ExternalBody|FunctionBody $body;

    /**
     * @var list<Characteristic> The ordered characteristics, including effective LANGUAGE
     */
    public readonly array $characteristics;

    /**
     * @param ExternalBody|FunctionBody $body The SQL instruction structure capable of containing RETURN, or external code
     * @param list<Characteristic> $characteristics The characteristics in written order
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the language or instruction structure is inconsistent
     */
    public function __construct(ExternalBody|FunctionBody $body, array $characteristics = [])
    {
        $this->characteristics = Check::listOf($characteristics, Characteristic::class, 'A routine holds characteristics.');
        (new BodyLanguage())->check($body, $this->characteristics);
        (new BodyStructure())->check($body, ProgramKind::Function);
        $this->body = $body;
    }

    /**
     * Writes the characteristics before the complete body.
     */
    public function render(Output $out): void
    {
        foreach ($this->characteristics as $characteristic) {
            $out->node($characteristic);
        }
        $out->node($this->body);
    }
}
