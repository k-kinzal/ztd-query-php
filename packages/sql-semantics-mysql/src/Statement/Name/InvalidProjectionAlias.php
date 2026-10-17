<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A select-list name that is forward, ambiguous, or refers to an outer aggregate.
 *
 * The candidate expressions remain available even when their fields have not
 * been resolved yet. MySQL reports a forward or aggregate reference as error
 * 1247, and ambiguity as error 1052.
 *
 * @visibility public
 * @example Describing a forward reference
 *     $problem = new \SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias(new \SqlSemantics\Statement\Identifier\Name('x'), \SqlSemantics\Platform\MySql\Statement\Name\AliasRule::Forward);
 *     $problem->message() // => "Reference 'x' not supported (forward reference in item list)"
 */
final class InvalidProjectionAlias implements Resolution, Diagnostic
{
    use Snapshot;

    /**
     * @param list<Scalar> $candidates The matching select expressions in declaration order
     */
    public function __construct(public readonly Name $name, public readonly AliasRule $rule, public readonly array $candidates = [])
    {
    }

    /**
     * Describes the rejected reference.
     */
    public function message(): string
    {
        return $this->rule === AliasRule::Ambiguous ? 'Column ' . $this->name->value . ' is ambiguous.' : sprintf("Reference '%s' not supported (%s)", $this->name->value, $this->rule->value);
    }
}
