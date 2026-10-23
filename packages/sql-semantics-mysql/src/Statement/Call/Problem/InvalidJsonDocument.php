<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * JSON_VALUE's document has a type that cannot supply JSON text or a JSON value.
 *
 * This is a preparation condition, before the constant path is interpreted.
 * A bare prepared marker has no permitted document type during preparation;
 * an explicit character or JSON cast gives it one. SQL NULL literals are allowed.
 * Observed through ordinary and prepared queries on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility public
 * @example Describing the document type condition
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT JSON_VALUE(1, '$')")->facts->diagnostics[0]->message() // => 'Invalid data type for JSON data in argument 1 to function json_value; a JSON string or JSON type is required.'
 */
final class InvalidJsonDocument implements Diagnostic
{
    use Snapshot;

    /**
     * @param \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall $expression The call that owns this preparation condition
     */
    public function __construct(public readonly \SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall $expression)
    {
    }

    /**
     * Describes the required document domain.
     */
    public function message(): string
    {
        return 'Invalid data type for JSON data in argument 1 to function json_value; a JSON string or JSON type is required.';
    }
}
