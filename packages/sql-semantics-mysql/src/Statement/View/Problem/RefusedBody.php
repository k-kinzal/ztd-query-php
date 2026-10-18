<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ViewQueries;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * A refused view input retaining its body, target and definer without an ordinary view value.
 *
 * Variables, parameters and result destinations cannot form a persistent view
 * body. Dependencies remain typed values for preceding input diagnostics.
 *
 * @visibility public
 * @example Refusing session inputs from a view definition
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE VIEW v AS SELECT @x') // throws \SqlSemantics\Construction\RejectedInput
 */
final class RefusedBody implements Diagnostic
{
    use Snapshot;

    /**
     * The intrinsic restriction derived from the retained body's read dependencies.
     */
    public readonly BodyRestriction $restriction;

    /**
     * @param QualifiedName $view The view's destination name
     * @param bool $alter Whether the input is ALTER VIEW
     * @param Query|DeferredSelection|DeferredQuery $body The complete body including a refused operand, when present
     * @param Account|null $definer The explicit account, when written
     */
    public function __construct(public readonly QualifiedName $view, public readonly bool $alter, public readonly Query|DeferredSelection|DeferredQuery $body, public readonly ?Account $definer = null)
    {
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($view);
        $restriction = (new ViewQueries())->problem($body);
        Check::input($restriction !== null, 'A refused view body retains a session input or result destination.');
        $this->restriction = $restriction;
    }

    /**
     * Describes the body's intrinsic restriction.
     */
    public function message(): string
    {
        return $this->restriction->message();
    }
}
