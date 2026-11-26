<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An SQL/XML constructor the server rejects.
 *
 * @visibility SqlSemantics
 * @example Describing a repeated attribute name
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblem(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblemKind::RepeatedAttribute, 'a'))->message() // => 'XML attribute name "a" appears more than once'
 */
final class XmlProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param XmlProblemKind $kind The mistake
     * @param string $subject The attribute name or the type name the message names; empty when it names none
     */
    public function __construct(public readonly XmlProblemKind $kind, public readonly string $subject = '')
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return str_replace('%s', $this->subject, $this->kind->value);
    }
}
