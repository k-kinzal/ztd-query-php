<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls;

use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;

/**
 * Resolves the fixed SQLite first non-NULL builtins.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CoalesceLowering
{
    /**
     * Converts COALESCE and binary IFNULL to the same ordered operand role.
     * @throws InvalidConstruction When the function does not have the checked role
     */
    public function lower(FunctionCall $call, SourceBinding $binding, ExpressionLowering $lowering, bool $preserveBuiltin): Coalesce
    {
        $name = strtr($call->name->value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
        Check::input(
            $name === 'coalesce' || ($name === 'ifnull' && count($call->arguments) === 2),
            'This expression has no complete SQLite scalar representation.'
        );
        Check::input(
            !$call->star && $call->order === [] && $call->filter === null && $call->over === null,
            'COALESCE requires ordinary scalar arguments.'
        );
        $arguments = [];
        foreach ($call->arguments as $argument) {
            $arguments[] = new CoalesceArgument($lowering->lower($argument, $binding));
        }

        return new Coalesce($arguments, $preserveBuiltin && $name === 'ifnull');
    }
}
