<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Value\Json\Json;
use MySqlMemory\Value\Json\JsonSyntax;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineAttributeOption;
use SqlSemantics\Platform\MySql\Statement\Table\Column\EngineAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexEngineAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption;
use SqlSemantics\Statement\Node;

/**
 * Checks the JSON text of engine attributes before table or tablespace lookup.
 *
 * The empty string is permitted; other values must be complete JSON documents. The error
 * records the parser's byte offset and the suffix beginning there. Verified on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility MySqlMemory
 */
final class EngineAttributes
{
    /**
     * Checks engine attributes on tables, columns, indexes and tablespaces.
     *
     * @throws \MySqlMemory\Error\SqlError When an attribute is not a JSON document
     */
    public function check(Node $statement): void
    {
        foreach ((new Walker())->find($statement, Node::class) as $node) {
            $text = match (true) {
                $node instanceof EngineAttribute, $node instanceof IndexEngineAttribute, $node instanceof EngineAttributeOption => $node->attribute,
                $node instanceof TextOption && in_array($node->kind, [TextOptionKind::EngineAttribute, TextOptionKind::SecondaryEngineAttribute], true) => $node->value,
                default => null,
            };
            if ($text === null || $text->bytes() === '') {
                continue;
            }
            $value = $text->bytes();
            try {
                Json::canonical($value);
            } catch (JsonSyntax $error) {
                throw SchemaError::InvalidEngineAttribute->error($error->reason, $error->position, substr($value, $error->position));
            }
        }
    }
}
