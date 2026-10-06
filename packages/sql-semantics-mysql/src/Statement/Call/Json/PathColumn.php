<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A `name type PATH path` or `name type EXISTS PATH path` column of JSON_TABLE, with its ON EMPTY and ON ERROR responses.
 *
 * Rule: MYSQL-JSON-TABLE-COLUMN-001 (path). The column has the declared type
 * and is NULL when the path finds nothing and by default on an error; an
 * EXISTS column is 1 or 0. The server accepts ON ERROR before ON EMPTY and
 * warns that the order is deprecated, so the written order is kept.
 * Terminates: the parts are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a path column
 *     $column = new \SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['$.a']));
 *     [$column->name->value, $column->type->name(), $column->exists] // => ['a', 'INT', false]
 * @example Refusing the reversed order without both responses
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['$.a']), errorFirst: true) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class PathColumn implements JsonTableColumn
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param TypeName $type The declared type
     * @param StringLiteral $path The path
     * @param bool $exists Whether the column is an EXISTS column
     * @param CollationName|null $collation The written collation
     * @param JsonResponse|null $onEmpty The ON EMPTY response
     * @param JsonResponse|null $onError The ON ERROR response
     * @param bool $errorFirst Whether ON ERROR is written before ON EMPTY
     */
    public function __construct(
        public readonly Name $name,
        public readonly TypeName $type,
        public readonly StringLiteral $path,
        public readonly bool $exists = false,
        public readonly ?CollationName $collation = null,
        public readonly ?JsonResponse $onEmpty = null,
        public readonly ?JsonResponse $onError = null,
        public readonly bool $errorFirst = false,
    ) {
        Check::input(!$errorFirst || ($onEmpty !== null && $onError !== null), 'ON ERROR is written first only when both responses are.');
    }

    /**
     * Derives the path and the defaults and answers the column.
     *
     * @return list<OutputSlot>
     */
    public function deriveColumns(Derivation $derivation, Environment $environment): array
    {
        (new Arguments())->one($this->path, $derivation, $environment);
        foreach ([$this->onEmpty?->default, $this->onError?->default] as $default) {
            if ($default !== null) {
                (new Arguments())->one($default, $derivation, $environment);
            }
        }

        return [new OutputSlot($this->name, new Known($this->type), Nullability::Nullable)];
    }

    /**
     * Writes the column.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column)->node($this->type);
        if ($this->collation !== null) {
            $out->keyword('COLLATE')->node($this->collation);
        }
        if ($this->exists) {
            $out->keyword('EXISTS');
        }
        $out->keyword('PATH')->node($this->path);
        $responses = [[$this->onEmpty, 'EMPTY'], [$this->onError, 'ERROR']];
        foreach ($this->errorFirst ? array_reverse($responses) : $responses as [$response, $condition]) {
            if ($response !== null) {
                $out->node($response)->keyword('ON', $condition);
            }
        }
    }
}
