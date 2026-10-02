<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Expression;

/**
 * Explicit grouping of a new expression, including its observable label.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Expression\GroupedInput(new \SqlSemantics\Statement\Expression\NullConstant());
 *     $input->before // => ''
 */
final class GroupedInput implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains explicit new inputs; resolution belongs to their new enclosing query.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\ScalarInput $operand, public readonly string $before = '', public readonly string $after = '')
    {
        \SqlSemantics\Statement\Validation\Check::input((new \SqlSemantics\Statement\Expression\Rendering\SqliteTrivia())->accepts($before) && (new \SqlSemantics\Statement\Expression\Rendering\SqliteTrivia())->accepts($after), 'Grouping gaps contain only complete trivia.');
        \SqlSemantics\Statement\Construction\InputDomain::check($operand);
    }
}
