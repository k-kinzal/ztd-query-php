<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\TableDefinition;

/**
 * MySQL implementation of SchemaParser using phpMyAdmin SQL parser.
 */
final class MySqlSchemaParser implements SchemaParser
{
    private MySqlParser $parser;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(MySqlParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * {@inheritDoc}
     */
    public function parse(string $createTableSql): ?TableDefinition
    {
        $statements = $this->parser->parse($createTableSql);
        if ($statements === []) {
            return null;
        }

        $stmt = $statements[0];
        if (!$stmt instanceof CreateStatement) {
            return null;
        }

        if (!is_iterable($stmt->fields)) {
            return null;
        }

        $builder = new Schema\DefinitionBuilder();
        foreach ($stmt->fields as $field) {
            if ($field->type !== null && ($field->name ?? '') === '') {
                continue;
            }
            $builder->addColumn($field);
            $builder->addKey($field);
        }
        return $builder->build(
            (new MySqlForeignKeyDefinitionParser())->parseCreateTable($createTableSql),
            is_string($stmt->partitionBy) ? (new MySqlPartitioningParser())->parse($stmt) : null,
        );
    }
}
