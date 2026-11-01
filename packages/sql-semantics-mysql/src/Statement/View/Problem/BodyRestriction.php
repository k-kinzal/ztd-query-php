<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * A view body cannot read session inputs or redirect its result.
 *
 * These restrictions hold independently of the catalog and session values.
 *
 * @visibility public
 * @example Describing a variable-dependent view body
 *     \SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction::Variable->message() // => "View's SELECT contains a variable or parameter"
 */
enum BodyRestriction implements Diagnostic
{
    case Variable;
    case Into;

    /**
     * Describes the forbidden view-body role.
     */
    public function message(): string
    {
        return match ($this) {
            self::Variable => "View's SELECT contains a variable or parameter",
            self::Into => "View's SELECT contains a 'INTO' clause",
        };
    }
}
