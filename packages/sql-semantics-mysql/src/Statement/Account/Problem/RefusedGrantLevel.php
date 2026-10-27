<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A grant target contradiction, retaining operands needed for earlier name resolution.
 *
 * @visibility public
 * @example Describing a retained target contradiction
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON PROCEDURE *.* TO u');
 *     $create->body->problem()->message() // => 'The privilege and object kind do not fit the grant level.'
 */
final class RefusedGrantLevel implements Diagnostic
{
    use Snapshot;

    /**
     * The first structural target condition, before any object or account lookup.
     */
    public readonly MisplacedPrivilege $condition;

    /**
     * @param GrantRequest $request The refused operands, never a standalone statement
     * @param bool $definition Whether legacy program definition rejects a column list before invocation
     */
    public function __construct(public readonly GrantRequest $request, public readonly bool $definition = false)
    {
        Check::input(!$definition || $request->columnLevel(), 'Only legacy column levels are refused during program definition.');
        $condition = $request->scopeProblem();
        Check::input($condition !== null, 'A grant-level refusal needs a contradictory privilege target.');
        $this->condition = $condition;
    }

    /**
     * Describes the refused target without retaining source SQL.
     */
    public function message(): string
    {
        return 'The privilege and object kind do not fit the grant level.';
    }
}
