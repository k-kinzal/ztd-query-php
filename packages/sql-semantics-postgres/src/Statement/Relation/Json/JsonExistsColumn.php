<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation\Json;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * name type EXISTS [PATH path] [ON ERROR]: a JSON_TABLE column that tells whether the path yields an item.
 *
 * Mirrors PostgreSQL's `JsonTableColumn` of type JTC_EXISTS. A column named
 * nested is written quoted: before a type named path gram.y reads the word as
 * the NESTED PATH of a nested column (NESTED has a lower precedence than PATH).
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-TABLE.
 *
 * @visibility SqlSemantics
 * @example Reading an EXISTS column
 *     $column = new \SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonExistsColumn(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('e'), new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation(\SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword::Boolean)));
 *     $column->path // => null
 */
final class JsonExistsColumn implements JsonTableColumn
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param TypeName $type The column type
     * @param StringConstant|null $path The path written after PATH
     * @param Clause|null $onError The ON ERROR behavior
     */
    public function __construct(public readonly Name $name, public readonly TypeName $type, public readonly ?StringConstant $path = null, public readonly ?Clause $onError = null)
    {
    }

    /**
     * Writes the column definition.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, $this->name->value === 'nested' ? NameUse::Identifier : NameUse::Column)->node($this->type)->keyword('EXISTS');
        if ($this->path !== null) {
            $out->keyword('PATH')->node($this->path);
        }
        $out->node($this->onError);
    }
}
