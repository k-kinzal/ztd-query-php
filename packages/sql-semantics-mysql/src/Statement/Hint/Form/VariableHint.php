<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Form;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Statement\Snapshot;

/**
 * SET_VAR(variable = value): the session value a system variable has while the statement runs.
 *
 * The variable is named without a scope, and not every session variable
 * can be set this way. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-set-var.
 *
 * @visibility public
 * @example Writing the hint
 *     (new \SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint('sort_buffer_size', new \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral(\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind::Word, '16M')))->text() // => 'SET_VAR(`sort_buffer_size` = `16M`)'
 */
final class VariableHint implements OptimizerHint
{
    use Snapshot;

    /**
     * @param string $variable The name of the system variable, as written
     * @param HintLiteral $value The value
     */
    public function __construct(public readonly string $variable, public readonly HintLiteral $value)
    {
        Check::input($variable !== '', 'A variable has a name.');
    }

    /**
     * Answers the name of the hint.
     */
    public function name(): HintName
    {
        return HintName::SetVar;
    }

    /**
     * Answers the hint as it is written in a hint comment, with the variable quoted.
     */
    public function text(): string
    {
        return 'SET_VAR(' . HintTable::quote($this->variable) . ' = ' . $this->value->text() . ')';
    }
}
