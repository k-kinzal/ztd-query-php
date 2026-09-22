<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction;

use SqlSemantics\Model\Expression;

/**
 * Collects evaluation inputs from document-table declarations without reading parser syntax.
 * @visibility SqlSemantics
 */
final class Dependencies
{
    /**
     * @return list<Expression>
     */
    public static function of(Json\JsonTable|Xml\XmlTable $table): array
    {
        if ($table instanceof Json\JsonTable) {
            return [$table->document->expression, $table->path, ...array_map(static fn (Json\PassingArgument $passing): Expression => $passing->input->expression, $table->passing), ...self::json($table->columns)];
        }
        $inputs = [$table->document, $table->rowPath, ...array_map(static fn (Xml\NamespaceBinding $namespace): Expression => $namespace->uri, $table->namespaces)];
        foreach ($table->columns as $column) {
            if ($column instanceof Xml\ValueColumn) {
                array_push($inputs, ...($column->path === null ? [] : [$column->path]), ...($column->default === null ? [] : [$column->default]));
            }
        }
        return $inputs;
    }

    /**
     * @param list<Json\Column> $columns
     * @return list<Expression>
     */
    public static function json(array $columns): array
    {
        $result = [];
        foreach ($columns as $column) {
            if ($column instanceof Json\NestedColumns) {
                array_push($result, $column->path, ...self::json($column->columns));
            } elseif ($column instanceof Json\ValueColumn || $column instanceof Json\ExistsColumn) {
                array_push($result, ...($column->path === null ? [] : [$column->path]));
                if ($column instanceof Json\ValueColumn && $column->onError instanceof Json\Response\DefaultResponse) {
                    $result[] = $column->onError->expression;
                }
                if ($column instanceof Json\ValueColumn && $column->onEmpty instanceof Json\Response\DefaultResponse) {
                    $result[] = $column->onEmpty->expression;
                }
            }
        }
        return $result;
    }
}
