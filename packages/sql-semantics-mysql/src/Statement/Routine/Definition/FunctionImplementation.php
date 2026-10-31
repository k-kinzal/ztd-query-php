<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Definition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A function's instruction body and the characteristics that interpret it.
 *
 * The body representation agrees with the LANGUAGE characteristic: external code
 * is written for a non-SQL language and SQL instructions for SQL. An SQL body
 * reaches a RETURN. CreateFunction checks the remaining stored program
 * restrictions after combining the implementation with its name, parameter list
 * and result declaration.
 *
 * @visibility public
 * @example Constructing a SQL implementation independently of a function name
 *     $implementation = new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')));
 *     count($implementation->characteristics) // => 0
 * @example Excluding values that are not instruction bodies
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()) // throws \TypeError
 * @example Refusing an SQL body without a RETURN
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class FunctionImplementation
{
    use Snapshot;

    /**
     * @var ExternalBody|ProgramStatement|Statement The complete body, including instructions rejected when the function is defined
     */
    public readonly ExternalBody|ProgramStatement|Statement $body;

    /**
     * @var list<Characteristic> The ordered characteristics, including effective LANGUAGE
     */
    public readonly array $characteristics;

    /**
     * @param ExternalBody|ProgramStatement|Statement $body The complete SQL instruction structure or external code
     * @param list<Characteristic> $characteristics The characteristics in written order
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a characteristic is not a routine characteristic, the language and the body disagree, or an SQL body never returns
     */
    public function __construct(ExternalBody|ProgramStatement|Statement $body, array $characteristics = [])
    {
        $this->characteristics = Check::listOf($characteristics, Characteristic::class, 'A routine holds characteristics.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyLanguage())->compatible($body, $this->characteristics), 'The routine language and the body representation agree.');
        Check::input($body instanceof ExternalBody || (new \SqlSemantics\Platform\MySql\Rules\Routine\ReturnSearch())->found($body), 'An SQL function body reaches a RETURN.');
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
