<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Json;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Plan\Path\Source\JsonColumn;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonKind;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;
use SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns;
use SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn;
use SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Prepares JSON_TABLE declarations from their already resolved output slots.
 *
 * @visibility MySqlMemory
 */
final class Columns
{
    /**
     * @param list<\SqlSemantics\Statement\Shape\OutputSlot> $slots Remaining resolved slots in declaration order
     * @throws SqlError When a path or default value cannot be prepared
     */
    public function compile(JsonTableColumn $column, array &$slots): JsonColumn
    {
        if ($column instanceof NestedColumns) {
            $nested = [];
            foreach ($column->columns as $inner) {
                $nested[] = $this->compile($inner, $slots);
            }
            return new JsonColumn('nested', '', Domain::null(), \MySqlMemory\Plan\Relations::jsonPath($column->path->value()), [JsonResponseKind::Null, null], [JsonResponseKind::Null, null], $nested);
        }
        $slot = array_shift($slots);
        $type = $slot?->type;
        \SqlSemantics\Diagnostic\Check::invariant($slot !== null && $type instanceof \SqlSemantics\Statement\Type\Known && $type->descriptor instanceof \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain, 'A JSON_TABLE column has a resolved semantic domain.');
        $domain = Domain::of($type->descriptor, $slot->nullability !== \SqlSemantics\Statement\Type\Nullability::NotNull);
        if ($column instanceof OrdinalityColumn) {
            return new JsonColumn('ordinality', $column->name->value, $domain);
        }
        if (!$column instanceof PathColumn) {
            throw StatementError::NotSupportedYet->error('a JSON_TABLE column');
        }
        $name = $column->name->value;
        $response = static function (?JsonResponse $response) use ($domain, $name): array {
            $default = $response?->default;
            if ($default === null) {
                return [$response->kind ?? JsonResponseKind::Null, null];
            }
            try {
                $value = Jsons::parse($default instanceof StringLiteral ? $default->value() : '', 1, 'JSON_TABLE');
            } catch (SqlError $failure) {
                throw new SqlError($failure->error, $failure->getMessage(), $failure, [[SchemaError::InvalidDefault->value, SchemaError::InvalidDefault->message($name)]]);
            }
            if ($domain->kind !== Kind::Json && ($value->type === JsonKind::Array || $value->type === JsonKind::Object)) {
                throw SchemaError::InvalidDefault->error($name);
            }

            return [JsonResponseKind::Default, $value];
        };

        return new JsonColumn($column->exists ? 'exists' : 'path', $name, $domain, \MySqlMemory\Plan\Relations::jsonPath($column->path->value()), $response($column->onEmpty), $response($column->onError));
    }

}
