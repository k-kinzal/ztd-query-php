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
            'IF' => static fn (Invocation $call): ?Domain => $call->aggregation()->of((new self())->branches($call, [1, 2]), 'if', $call->derivation),
            'IFNULL' => static fn (Invocation $call): ?Domain => $call->aggregation()->of((new self())->branches($call, array_keys($call->domains)), 'ifnull', $call->derivation),
            'COALESCE' => static fn (Invocation $call): ?Domain => $call->aggregation()->of((new self())->branches($call, array_keys($call->domains)), 'coalesce', $call->derivation),
            'NULLIF' => static fn (Invocation $call): Domain => $call->domain(0),
        ];
    }

    /**
     * Infers absent user-variable operands from the other result branches.
     *
     * Explicitly initialized variables retain their recorded type. Each absent
     * occurrence is inferred independently from the first other resolved branch,
     * including a literal NULL; this does not create a session entry. The remaining
     * branches take part only in the final aggregation of result types.
     * Verified through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
     *
     * @param list<int> $positions The result-argument positions
     * @return list<Domain>
     */
    public function branches(Invocation $call, array $positions): array
    {
        $domains = array_map($call->domain(...), $positions);
        $nodes = array_map(static fn (int $position): ?\SqlSemantics\Statement\Scalar => $call->nodes[$position] ?? null, $positions);

        return (new \SqlSemantics\Platform\MySql\Rules\Typing\BranchInference())->resolve($domains, $nodes, $call->derivation);
    }
}
