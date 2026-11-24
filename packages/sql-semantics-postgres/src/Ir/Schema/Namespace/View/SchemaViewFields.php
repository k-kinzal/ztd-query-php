<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\CompoundShape;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\ResultKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Derives the physical kind and collation identity of each declared view field.
 *
 * @visibility SqlSemantics
 */
final class SchemaViewFields
{
    /**
     * Returns the current native heading. A null collation selects the default for text.
     *
     * @param array<array-key, array<array-key, array{ColumnKind, TextCollation|array{string,string,string,string}|null}>> $earlier
     * @return array<array-key, array{ColumnKind, TextCollation|array{string,string,string,string}|null}>
     */
    public static function of(CreateView $view, array $earlier): array
    {
        $query = $view->query;
        $fields = [];
        foreach ($view->columns as $position => $column) {
            $kind = match (CompoundShape::type($query, $position)) {
                ResultKind::Integer => ColumnKind::Int4,
                ResultKind::WideInteger => ColumnKind::Int8,
                ResultKind::Boolean => ColumnKind::Boolean,
                ResultKind::Text, ResultKind::Unknown => ColumnKind::Text,
            };
            $collation = null;
            if ($kind === ColumnKind::Text && $query instanceof Select) {
                $expression = $query->columns[$position]->expression;
                while ($expression instanceof BuiltinCast) {
                    $expression = $expression->operand;
                }
                if ($expression instanceof CollatedText) {
                    $collation = $expression->collation;
                } elseif ($expression instanceof TextColumnReference) {
                    $source = $query->source;
                    Check::input($source instanceof TableSource, 'A view field requires its checked source.');
                    $field = $source->columns[$expression->slot]->value;
                    if ($source->schema->value !== $view->name->schema->value) {
                        $collation = [$source->kind->value, $source->schema->value, $source->table->value, $field];
                    } elseif ($source->kind === ExternalRelationKind::View) {
                        $collation = $earlier[$source->table->value][$field][1];
                    }
                }
            }
            $fields[$column->value] = [$kind, $collation];
        }

        return $fields;
    }
}
