<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Syntax\NodeReader;
use SqlParser\Parser\Node;

/**
 * Reads a columnDef node into a schema column.
 *
 * @visibility root
 */
final class ColumnParser
{
    /**
     * Returns the column the node declares, or null when it names no typed column.
     *
     * @param list<string> $tablePrimaryKeys
     */
    public function parseColumnDefinition(Node $columnDef, array $tablePrimaryKeys): ?ColumnDefinition
    {
        $reader = new NodeReader();
        $nameToken = $reader->firstToken($columnDef);
        $typename = $reader->child($columnDef, 'Typename');
        if ($nameToken === null || $typename === null) {
            return null;
        }
        $name = (new Identifier())->decode($nameToken);
        $constraints = (new ColumnConstraints())->read($columnDef);
        $shape = (new TypeDeclaration())->parse($typename);
        $autoIncrement = $shape->autoIncrement || $constraints->identity;
        $primaryKey = $constraints->primaryKey || in_array($name, $tablePrimaryKeys, true);
        $default = $constraints->default === null ? null : (new DefaultExpression())->evaluate($constraints->default);

        return new ColumnDefinition(
            name: $name,
            type: $shape->type,
            length: $shape->length,
            precision: $shape->precision,
            scale: $shape->scale,
            nullable: $constraints->nullable && !$primaryKey && !$autoIncrement,
            unsigned: false,
            default: $default,
            autoIncrement: $autoIncrement,
            generated: $constraints->generated,
            enumValues: null,
        );
    }
}
