<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml\Handler;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Constrains HANDLER expressions independently of open handles and tables.
 *
 * Subqueries have no interpretation in this command, even in an unselected
 * branch. Stored functions are also excluded independently of routine existence.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html,
 * https://dev.mysql.com/doc/refman/8.4/en/stored-program-restrictions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class HandlerOperands
{
    /**
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a read contains a subquery or requires stored functions
     */
    public function check(Node $root): void
    {
        $problem = $this->problem($root);
        Check::input($problem === null, $problem ?? '');
    }

    /**
     * Describes what a HANDLER read cannot contain, or null when the read is accepted.
     */
    public function problem(Node $root): ?string
    {
        $pending = [$root];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof Node) {
                if ($value instanceof Query) {
                    return 'A HANDLER read cannot contain a subquery.';
                }
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
        return $this->releases([$root]) !== 0 ? null : 'A HANDLER read requires a native interpretation of every function call.';
    }

    /**
     * Finds the releases in which all calls are native, before resolving any catalog object.
     * Signature constraints are intersected separately by the enclosing structure.
     *
     * @param list<Node> $operands The key values and predicate, or their enclosing read
     */
    public function releases(array $operands): int
    {
        $releases = ReleaseDomain::ALL;
        $pending = $operands;
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof Node) {
                if ($value instanceof FunctionCall || $value instanceof DeferredCall) {
                    foreach (GrammarRelease::cases() as $release) {
                        $bit = (new ReleaseDomain())->bit($release);
                        if (($releases & $bit) !== 0 && !$value->native($release)) {
                            $releases &= ~$bit;
                        }
                    }
                }
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
        return $releases;
    }
}
