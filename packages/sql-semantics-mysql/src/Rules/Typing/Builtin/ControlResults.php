<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;

/**
 * Resolves the results of flow control functions: IF, IFNULL and COALESCE settle the types of their branches, NULLIF keeps the type of its first argument.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class ControlResults
{
    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        return [
            'IF' => static fn (Invocation $call): ?Domain => $call->aggregation()->of([$call->domain(1), $call->domain(2)], 'if', $call->derivation),
            'IFNULL' => static fn (Invocation $call): ?Domain => $call->aggregation()->of($call->domains, 'ifnull', $call->derivation),
            'COALESCE' => static fn (Invocation $call): ?Domain => $call->aggregation()->of($call->domains, 'coalesce', $call->derivation),
            'NULLIF' => static fn (Invocation $call): Domain => $call->domain(0),
        ];
    }
}
