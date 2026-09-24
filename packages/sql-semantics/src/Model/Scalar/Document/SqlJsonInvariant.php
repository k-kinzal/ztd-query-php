<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\TableFunction\Json\PassingArgument;
use SqlSemantics\Model\TableFunction\Json\Response\DefaultResponse;
use SqlSemantics\Model\TableFunction\Json\Response\ValueResponse;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Shared operand rules of the SQL/JSON functions: one SQL dialect and the expressions nested in options.
 * @visibility SqlSemantics
 */
final class SqlJsonInvariant
{
    /**
     * Requires every operand to belong to the given dialect.
     * @param list<Expression> $operands
     * @throws InvalidStructure
     */
    public static function dialect(string $operation, Dialect $dialect, array $operands): void
    {
        foreach ($operands as $operand) {
            if ($operand->type->dialect !== $dialect) {
                throw new InvalidStructure($operation . ' operands must share one SQL dialect.');
            }
        }
    }

    /**
     * Requires every operand to be a PostgreSQL expression; the SQL/JSON constructors and query functions other than JSON_VALUE are PostgreSQL's.
     * @param list<Expression> $operands
     * @throws InvalidStructure
     */
    public static function postgreSql(string $operation, array $operands): void
    {
        self::dialect($operation, Dialect::PostgreSql, $operands);
    }

    /**
     * @param list<PassingArgument> $passing
     * @return list<Expression> The PASSING values in written order
     */
    public static function passed(array $passing): array
    {
        return array_map(static fn (PassingArgument $argument): Expression => $argument->input->expression, $passing);
    }

    /**
     * @param list<Input> $inputs
     * @return list<Expression> The formatted values in written order
     */
    public static function values(array $inputs): array
    {
        return array_map(static fn (Input $input): Expression => $input->expression, $inputs);
    }

    /**
     * @return list<Expression> The DEFAULT expressions of the given responses
     */
    public static function defaults(ValueResponse ...$responses): array
    {
        return array_values(array_map(static fn (DefaultResponse $response): Expression => $response->expression, array_filter($responses, static fn (ValueResponse $response): bool => $response instanceof DefaultResponse)));
    }

    /**
     * @param list<Expression> $operands
     * @return list<string> Relation occurrences whose outer-join NULL rows reach any operand
     */
    public static function extensions(array $operands): array
    {
        $ids = [];
        foreach ($operands as $operand) {
            array_push($ids, ...$operand->nullExtendedBy);
        }
        return array_values(array_unique($ids));
    }
}
