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
 * name type [FORMAT JSON] [PATH path] [wrapper] [quotes] [behavior]: a JSON_TABLE column read from the row.
 *
 * Mirrors PostgreSQL's `JsonTableColumn` of type JTC_REGULAR, or
 * JTC_FORMATTED when a FORMAT clause is written. A column named nested is
 * written quoted: before a type named path gram.y reads the word as the
 * NESTED PATH of a nested column (NESTED has a lower precedence than PATH).
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-TABLE.
 *
 * @visibility SqlSemantics
 */
final class JsonValueColumn implements JsonTableColumn
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param TypeName $type The column type
     * @param StringConstant|null $path The path written after PATH
     * @param Clause|null $format The FORMAT clause
     * @param Clause|null $wrapper The wrapper behavior
     * @param Clause|null $quotes The quotes behavior
     * @param Clause|null $behavior The ON EMPTY and ON ERROR behaviors
     */
    public function __construct(
        public readonly Name $name,
        public readonly TypeName $type,
        public readonly ?StringConstant $path = null,
        public readonly ?Clause $format = null,
        public readonly ?Clause $wrapper = null,
        public readonly ?Clause $quotes = null,
        public readonly ?Clause $behavior = null,
    ) {
    }

    /**
     * Writes the column definition.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, $this->name->value === 'nested' ? NameUse::Identifier : NameUse::Column)->node($this->type)->node($this->format);
        if ($this->path !== null) {
            $out->keyword('PATH')->node($this->path);
        }
        $out->node($this->wrapper)->node($this->quotes)->node($this->behavior);
    }
}
