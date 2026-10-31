<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation\Json;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * name FOR ORDINALITY: a JSON_TABLE column that numbers the rows.
 *
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-TABLE.
 *
 * @visibility public
 * @example Reading an ordinality column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonOrdinalityColumn(new \SqlSemantics\Statement\Identifier\Name('n')))->name->value // => 'n'
 */
final class JsonOrdinalityColumn implements JsonTableColumn
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $name The column name
     */
    public function __construct(public readonly Name $name, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Writes the name and FOR ORDINALITY.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column)->keyword('FOR', 'ORDINALITY');
    }
}
