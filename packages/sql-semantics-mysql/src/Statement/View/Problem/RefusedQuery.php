<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View\Problem;

use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery as QueryInput;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A view input whose read body has an intrinsic column or operand condition.
 *
 * The target identity and command kind remain available before preparing the
 * body. ALTER checks that target after opening read dependencies; CREATE checks
 * its query before deciding whether it may replace the target. No source text
 * or invalid ordinary view statement is retained.
 *
 * @example Retaining a refused body and the ALTER target
 *     $body = new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()])));
 *     (new \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedQuery(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v')), true, $body))->message() // => 'No tables used'
 *
 * @visibility public
 */
final class RefusedQuery implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $view The target view
     * @param bool $alter Whether this is ALTER VIEW
     * @param QueryInput|RefusedSelection $query The complete retained read dependencies
     * @param \SqlSemantics\Platform\MySql\Statement\Name\Account|null $definer The account checked before preparing the view body
     */
    public function __construct(public readonly QualifiedName $view, public readonly bool $alter, public readonly QueryInput|RefusedSelection $query, public readonly ?\SqlSemantics\Platform\MySql\Statement\Name\Account $definer = null)
    {
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($view);
    }

    /**
     * Describes the body condition; target and dependency conditions may precede it.
     */
    public function message(): string
    {
        return $this->query->message();
    }
}
