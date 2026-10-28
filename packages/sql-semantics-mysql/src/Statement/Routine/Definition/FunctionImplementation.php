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
 * SQL instructions and opaque external code remain available even when their
 * language, bindings or instruction arrangement would be rejected by the server.
 * CreateFunction derives those conditions after combining the implementation
 * with its name, parameter list and result declaration.
 *
 * @visibility public
 * @example Constructing a SQL implementation independently of a function name
 *     $implementation = new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')));
 *     count($implementation->characteristics) // => 0
 * @example Excluding values that are not instruction bodies
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()) // throws \TypeError
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
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a characteristic is not a routine characteristic
     */
    public function __construct(ExternalBody|ProgramStatement|Statement $body, array $characteristics = [])
    {
        $this->characteristics = Check::listOf($characteristics, Characteristic::class, 'A routine holds characteristics.');
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
