<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Maintenance;

use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Value\Json\Json;
use MySqlMemory\Value\Json\JsonSyntax;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\DropHistogram;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\Histogram;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\LoadHistogram;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\UpdateHistogram;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Creates and removes the histogram statistics of the columns of a table, as ANALYZE TABLE with a histogram clause does.
 *
 * A column named twice is refused before any table is opened. A temporary table has no
 * histograms. The columns are handled in the binary order of their names, each with its own row:
 * a column that does not exist, a JSON or spatial column, and a column a single-part unique index
 * covers are errors. JSON data builds the histogram of one column; the emulator checks only that
 * the data is a JSON object with a histogram type and a data type. Every rule was verified on a
 * live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 *
 * @visibility MySqlMemory
 */
final class Histograms
{
    /**
     * Refuses a column named twice, compared without regard to case.
     *
     * @throws \MySqlMemory\Error\SqlError When a column is named twice
     */
    public function check(Histogram $histogram): void
    {
        assert($histogram instanceof UpdateHistogram || $histogram instanceof DropHistogram || $histogram instanceof LoadHistogram);
        $seen = [];
        foreach ($histogram->columns as $column) {
            if (isset($seen[mb_strtolower($column->value)])) {
                throw ErrorCode::DuplicateFieldName->error($column->value);
            }
            $seen[mb_strtolower($column->value)] = true;
        }
    }

    /**
     * Changes the histograms of a table and answers the Msg_type and Msg_text of each row.
     *
     * @return list<array{string, string}>
     */
    public function rows(Histogram $histogram, StoredTable $table): array
    {
        assert($histogram instanceof UpdateHistogram || $histogram instanceof DropHistogram || $histogram instanceof LoadHistogram);
        if ($table->definition->temporary) {
            return [['Error', 'Cannot create histogram statistics for a temporary table.']];
        }
        if ($histogram instanceof DropHistogram) {
            return $this->drop($histogram->columns, $table);
        }
        if ($histogram instanceof LoadHistogram && count($histogram->columns) > 1) {
            return [['Error', 'Only one column can be specified while modifying histogram statistics with JSON data.']];
        }
        $named = [];
        foreach ($histogram->columns as $column) {
            $position = $table->definition->position($column->value);
            $named[] = [$position === null ? $column->value : $table->definition->columns[$position]->name, $position];
        }
        usort($named, static fn (array $left, array $right): int => strcmp($left[0], $right[0]));
        $rows = [];
        foreach ($named as [$name, $position]) {
            $problem = $this->problem($table, $name, $position);
            if ($problem === null && $histogram instanceof LoadHistogram) {
                return $this->load($histogram->data->value, $name, $table);
            }
            if ($problem === null) {
                $table->histograms[strtolower($name)] = $name;
            }
            $rows[] = $problem === null ? ['status', "Histogram statistics created for column '" . $name . "'."] : ['Error', $problem];
        }

        return $rows;
    }

    /**
     * Removes the histograms of columns and answers a row for each.
     *
     * @param list<Name> $columns
     * @return list<array{string, string}>
     */
    public function drop(array $columns, StoredTable $table): array
    {
        $names = array_map(static fn (Name $column): string => $column->value, $columns);
        sort($names, SORT_STRING);
        $rows = [];
        foreach ($names as $name) {
            if (isset($table->histograms[strtolower($name)])) {
                unset($table->histograms[strtolower($name)]);
                $rows[] = ['status', "Histogram statistics removed for column '" . $name . "'."];
            } else {
                $rows[] = ['Error', "No histogram statistics found for column '" . $name . "'."];
            }
        }

        return $rows;
    }

    /**
     * Answers why a column cannot have a histogram, or null when it can.
     */
    public function problem(StoredTable $table, string $name, ?int $position): ?string
    {
        if ($position === null) {
            return "The column '" . $name . "' does not exist.";
        }
        $field = $table->definition->columns[$position]->domain->field;
        if ($field === Field::Json || $field === Field::Geometry) {
            return "The column '" . $name . "' has an unsupported data type.";
        }
        foreach ($table->definition->keys as $key) {
            if (($key->kind === KeyKind::Primary || $key->kind === KeyKind::Unique) && $key->columns === [$position]) {
                return "The column '" . $name . "' is covered by a single-part unique index.";
            }
        }

        return null;
    }

    /**
     * Builds the histogram of a column from JSON data and answers its rows.
     *
     * @return list<array{string, string}>
     */
    public function load(string $data, string $name, StoredTable $table): array
    {
        $failed = ['Error', "Unable to build histogram statistics for column '" . $name . "' in table '" . $table->definition->schema . "'.'" . $table->definition->name . "'"];
        try {
            Json::canonical($data);
        } catch (JsonSyntax $failure) {
            return [['Error', ErrorCode::InvalidJsonTextInParameter->message(1, 'UPDATE HISTOGRAM', $failure->reason, $failure->position)], $failed, ['Error', 'JSON format error.']];
        }
        $document = json_decode($data, true);
        if (!is_array($document) || array_is_list($document) && $document !== [] || ltrim($data)[0] !== '{') {
            return [$failed, ['Error', 'JSON data is not an object']];
        }
        foreach (['histogram-type', 'data-type'] as $attribute) {
            if (!array_key_exists($attribute, $document)) {
                return [$failed, ['Error', "Missing attribute at '$.\"" . $attribute . "\"'."]];
            }
        }
        $table->histograms[strtolower($name)] = $name;

        return [['status', "Histogram statistics created for column '" . $name . "'."]];
    }
}
