<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Syntax\NodeReader;
use SqlParser\Parser\Node;

/**
 * Reads a columnname node and its constraint list into a schema column.
 *
 * @visibility root
 */
final class ColumnParser
{
    /**
     * Returns the column the nodes declare, or null when the name is missing.
     *
     * @param list<string> $tablePrimaryKeys
     */
    public function parseColumnDefinition(Node $columnname, Node $carglist, array $tablePrimaryKeys): ?ColumnDefinition
    {
        $reader = new NodeReader();
        $nameNode = $reader->child($columnname, 'nm');
        $nameToken = $nameNode === null ? null : $reader->firstToken($nameNode);
        $typetoken = $reader->child($columnname, 'typetoken');
        if ($nameToken === null || $typetoken === null) {
            return null;
        }
        $name = (new Identifier())->decode($nameToken);
        $constraints = (new ColumnConstraints())->read($carglist);
        $shape = (new TypeDeclaration())->parse($typetoken);
        $primaryKey = $constraints->primaryKey || in_array($name, $tablePrimaryKeys, true);
        $default = $constraints->default === null ? null : (new DefaultExpression())->extractDefault($constraints->default);

        return new ColumnDefinition(
            name: $name,
            type: $shape->type,
            length: $shape->length,
            precision: $shape->precision,
            scale: $shape->scale,
            nullable: $constraints->nullable && !$primaryKey,
            unsigned: false,
            default: $default,
            autoIncrement: $constraints->autoIncrement,
            generated: $constraints->generated,
            enumValues: null,
        );
    }
}
