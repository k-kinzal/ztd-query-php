<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction;

use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Function\DocumentColumn;
use SqlSemantics\Type\Identity;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Derives ordered result columns from declared document-table operations.
 * @visibility SqlSemantics
 */
final class OutputColumns
{
    /**
     * @return list<OutputColumn>
     */
    public static function derive(Json\JsonTable|Xml\XmlTable $table, Node $source, Dialect $dialect): array
    {
        $declarations = $table instanceof Json\JsonTable ? self::json($table->columns) : array_map(static fn ($column): array => [$column, false], $table->columns);
        $outputs = [];
        foreach ($declarations as [$column, $nested]) {
            $ordinal = $column instanceof Json\Ordinality || $column instanceof Xml\Ordinality;
            $type = self::type($column, $dialect);
            $notNull = !$nested && ($ordinal || $column instanceof Xml\ValueColumn && $column->notNull);
            $expression = new DocumentColumn(new ExpressionFacts($type, $notNull ? Nullability::NotNull : Nullability::MaybeNull), $source, $table, $column);
            $outputs[] = new OutputColumn(count($outputs), $column->name, $expression);
        }
        return $outputs;
    }

    /**
     * @param list<Json\Column> $columns
     * @return list<array{Json\Ordinality|Json\ValueColumn|Json\ExistsColumn, bool}>
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function json(array $columns, bool $nested = false): array
    {
        $result = [];
        foreach ($columns as $column) {
            if ($column instanceof Json\NestedColumns) {
                array_push($result, ...self::json($column->columns, true));
            } elseif ($column instanceof Json\Ordinality || $column instanceof Json\ValueColumn || $column instanceof Json\ExistsColumn) {
                $result[] = [$column, $nested];
            } else {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('Unclassified JSON_TABLE column.');
            }
        }
        return $result;
    }

    /**
     * Returns the storage type required by one declared document output.
     */
    public static function type(Json\Ordinality|Json\ValueColumn|Json\ExistsColumn|Xml\Ordinality|Xml\ValueColumn $column, Dialect $dialect): TypeDescriptor
    {
        if ($column instanceof Json\Ordinality || $column instanceof Xml\Ordinality) {
            return new TypeDescriptor($dialect, $dialect === Dialect::MySql ? new Identity\Numeric\IntegerStorage(Identity\BuiltinIdentity::Integer, unsigned: true) : Identity\BuiltinIdentity::Integer);
        }
        return $column->type;
    }
}
