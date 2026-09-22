<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Keeps a document operation's language, declared storage types, and path forms consistent.
 * @visibility SqlSemantics
 */
final class DocumentInvariant
{
    /**
     * @throws InvalidStructure
     */
    public static function check(Json\JsonTable|Xml\XmlTable $table): void
    {
        if ($table->dialect === Dialect::Sqlite || $table instanceof Xml\XmlTable && $table->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('The document-table operation does not belong to this SQL dialect.');
        }
        foreach (Dependencies::of($table) as $expression) {
            if ($expression->type->dialect !== $table->dialect) {
                throw new InvalidStructure('Document-table expressions must share one SQL dialect.');
            }
        }
        $columns = $table instanceof Json\JsonTable ? array_column(OutputColumns::json($table->columns), 0) : $table->columns;
        foreach ($columns as $column) {
            if (($column instanceof Json\ValueColumn || $column instanceof Json\ExistsColumn || $column instanceof Xml\ValueColumn) && $column->type->dialect !== $table->dialect) {
                throw new InvalidStructure('Document-table columns must use the document dialect.');
            }
        }
        if ($table instanceof Json\JsonTable && $table->dialect === Dialect::MySql) {
            self::mysql($table);
        }
    }

    /**
     * @throws InvalidStructure
     */
    public static function mysql(Json\JsonTable $table): void
    {
        if (!$table->path instanceof Literal || $table->path->literalKind !== LiteralKind::Text || $table->document->format !== null || $table->passing !== [] || $table->pathName !== null || $table->onError !== Json\Response\TableError::Default) {
            throw new InvalidStructure('MySQL JSON_TABLE requires a literal row path and its own supported document options.');
        }
        foreach (OutputColumns::json($table->columns) as [$column]) {
            if ($column instanceof Json\ValueColumn && ($column->format !== null || $column->wrapper !== Json\ArrayWrapping::Default || $column->quotes !== Json\Quotes::Default)) {
                throw new InvalidStructure('SQL/JSON wrapper and format options require PostgreSQL.');
            }
            if (($column instanceof Json\ValueColumn || $column instanceof Json\ExistsColumn) && $column->path === null) {
                throw new InvalidStructure('MySQL JSON_TABLE value columns require an explicit path.');
            }
        }
    }
}
