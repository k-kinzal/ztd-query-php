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
     * occurrence is inferred independently; this does not create a session entry.
     * Verified through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
     *
     * @param list<int> $positions The result-argument positions
     * @return list<Domain>
     */
    public function branches(Invocation $call, array $positions): array
    {
        $domains = array_map($call->domain(...), $positions);
        if (in_array($call->derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            return $domains;
        }
        $facts = $call->derivation->facts();
        $absent = [];
        foreach ($positions as $index => $position) {
            $node = $call->nodes[$position] ?? null;
            while ($node instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped) {
                $node = $node->operand;
            }
            $binding = $node !== null && $facts->covers($node) ? $facts->scalar($node)->resolution : null;
            if ($binding instanceof \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableBinding && !$binding->exists) {
                $absent[] = $index;
            }
        }
        $known = array_values(array_diff_key($domains, array_flip($absent)));
        $inferred = $absent === [] || $known === [] ? null : $call->aggregation()->of($known, 'coalesce', $call->derivation);
        if ($inferred !== null && ($inferred->kind->numeric() || $inferred->kind->temporal() || $inferred->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String)) {
            $variable = (new \SqlSemantics\Platform\MySql\Rules\Typing\Variables($call->settings, $call->derivation->context->profile->grammar))->inferred($inferred);
            foreach ($absent as $index) {
                $domains[$index] = $variable;
            }
        }

        return array_values($domains);
    }
}
