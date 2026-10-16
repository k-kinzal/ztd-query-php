<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An AT TIME ZONE conversion whose resolved operand or zone is unsuitable.
 *
 * The conversion owns the condition. Its operand is resolved first, so a
 * missing column inside it precedes this condition, while a later expression
 * does not. A dependent operand is not rejected until its type is available.
 * Observed through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility public
 * @example Reading the conversion condition before a later missing column
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT CAST(1 AT TIME ZONE '+00:00' AS DATETIME), missing");
 *     $operation->facts->diagnostics[0]->message() // => 'Cannot cast value to TIMESTAMP WITH TIME ZONE.'
 */
final class TimeZoneConversion implements Diagnostic
{
    use Snapshot;

    /**
     * @param AtTimeZone $expression The conversion that owns the condition
     * @param bool $operand Whether the operand type is unsuitable, rather than the zone
     */
    public function __construct(public readonly AtTimeZone $expression, public readonly bool $operand)
    {
    }

    /**
     * Describes the conversion condition in the words of the server.
     */
    public function message(): string
    {
        return $this->operand ? 'Cannot cast value to TIMESTAMP WITH TIME ZONE.' : "Unknown or incorrect time zone: '" . $this->expression->zone->value . "'";
    }
}
