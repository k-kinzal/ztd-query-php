<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An explanation retaining an absent DELETE target and its enclosing options.
 *
 * This is an input refusal, not a storable instruction. The target namespace
 * cannot supply the requested write, but an explicit explanation database is
 * resolved first. No original SQL or parser node is retained.
 *
 * @visibility public
 * @example Retaining the explanation around an impossible target namespace
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('EXPLAIN FOR DATABASE d DELETE x FROM t AS y') // throws \SqlSemantics\Construction\RejectedInput
 */
final class RefusedExplainTargets implements Diagnostic
{
    use Snapshot;

    /**
     * @param RefusedDeleteTargets $targets The complete rejected target namespace and input relations
     * @param Name|null $format The explicit report format
     * @param UserVariable|null $into The report destination
     * @param Name|null $database The database in which the explanation binds its operand
     * @param bool $analyze Whether execution measurements are requested
     * @param ExplainModifier|null $modifier The legacy report modifier
     * @param bool $emptyInto Whether an ignored empty destination spelling is present
     */
    public function __construct(public readonly RefusedDeleteTargets $targets, public readonly ?Name $format = null, public readonly ?UserVariable $into = null, public readonly ?Name $database = null, public readonly bool $analyze = false, public readonly ?ExplainModifier $modifier = null, public readonly bool $emptyInto = false)
    {
        Check::input((new ExplainFacts())->known($format), 'An EXPLAIN format is TRADITIONAL, JSON or TREE.');
        Check::input($into === null || strcasecmp($format->value ?? '', 'JSON') === 0, 'EXPLAIN INTO has an explicit JSON format.');
        Check::input(!$emptyInto || ($into === null && !$analyze && $modifier === null), 'An empty EXPLAIN destination has no assignment, ANALYZE or legacy modifier.');
        Check::input($modifier === null || ($format === null && !$analyze && $into === null && $database === null && !$emptyInto), 'EXTENDED and PARTITIONS are written alone.');
    }

    /**
     * Describes the absent target after preceding explanation dependencies resolve.
     */
    public function message(): string
    {
        return $this->targets->message();
    }
}
