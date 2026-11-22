<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Query;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Derives the ordered names and declaration types of a population query.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CreateAsLayout
{
    /**
     * Derives the layout of a checked source-free query.
     * @return non-empty-list<CreatedColumn>
     */
    public function columns(Select|ValuesQuery $query): array
    {
        if ($query instanceof ValuesQuery) {
            return $this->values($query);
        }
        $types = new CreateAsAffinity();
        $columns = [];
        $used = [];
        foreach ($query->columns as $slot => $column) {
            $name = $this->name($column->name, $slot, $used);
            $used[$name->key()] = true;
            $columns[] = new CreatedColumn($name, $types->declared($types->affinity($column->expression)));
        }

        return $columns;
    }

    /**
     * Assigns ordinal names and projects each column's structural operands.
     * @return non-empty-list<CreatedColumn>
     */
    public function values(ValuesQuery $query): array
    {
        $types = new CreateAsAffinity();
        $columns = [];
        $first = $query->rows[0]->values;
        $compound = count($query->rows) > 1 && $this->hasAffinity($query, $types);
        foreach ($first as $slot => $expression) {
            $operands = [];
            foreach ($query->rows as $row) {
                $operands[] = $row->values[$slot];
            }
            $type = $compound ? $types->compound($operands) : $types->declared($types->affinity($expression));
            $columns[] = new CreatedColumn(new Identifier('column' . ($slot + 1)), $type);
        }

        return $columns;
    }

    /**
     * Checks whether SQLite keeps the VALUES rows available for compound layout.
     */
    public function hasAffinity(ValuesQuery $query, CreateAsAffinity $types): bool
    {
        foreach ($query->rows[0]->values as $value) {
            if ($types->affinity($value) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolves SQLite's deterministic column name candidates.
     *
     * @param array<string, true> $used Previously assigned name keys
     */
    public function name(Identifier $label, int $slot, array $used): Identifier
    {
        $key = $label->key();
        $name = $key === 'true' || $key === 'false' ? new Identifier('column' . ($slot + 1)) : $label;
        for ($suffix = 1; $suffix <= 4 && isset($used[$name->key()]); $suffix++) {
            $stem = preg_match('/^(.*):[0-9]*$/sD', $name->value, $parts) === 1 ? $parts[1] : $name->value;
            $name = new Identifier($stem . ':' . $suffix);
        }
        Check::input(!isset($used[$name->key()]), 'CREATE TABLE AS requires deterministic result column names.');

        return $name;
    }
}
