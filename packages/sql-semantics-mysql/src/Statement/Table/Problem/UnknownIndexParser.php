<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A WITH PARSER name missing from the supplied full-text parser catalog.
 *
 * @visibility public
 * @example Reading the missing plugin name
 *     $problem = new \SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownIndexParser(new \SqlSemantics\Statement\Identifier\Name('custom_parser'));
 *     $problem->parser->value // => 'custom_parser'
 */
final class UnknownIndexParser implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $parser The requested parser plugin
     */
    public function __construct(public readonly Name $parser)
    {
    }

    /**
     * Describes the unresolved plugin dependency.
     */
    public function message(): string
    {
        return "Function '" . $this->parser->value . "' is not defined";
    }
}
