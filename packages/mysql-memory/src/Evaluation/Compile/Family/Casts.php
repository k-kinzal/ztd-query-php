<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Evaluation\Operator\Zoned;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles CAST and CONVERT to a type: the domain of the target and the conversion into it.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Casts
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles the conversion of an operand to a target type.
     *
     * @throws \MySqlMemory\Error\SqlError When the target is not one the emulator converts to
     */
    public function cast(Evaluable $operand, CastTarget $target, Scalar $node): Evaluable
    {
        if ($this->compiler->domain($node)->field === Field::Geometry) {
            return new \MySqlMemory\Evaluation\Function\Spatial\SpatialCast($operand, $this->compiler->domain($node), $target->kind->value);
        }

        return new Conversion($operand, $this->compiler->domain($node)->withSource($target->kind === CastKind::Json ? 'cast_as_json' : ''), \SqlSemantics\Platform\MySql\Rules\Typing\Casts::stringLimit($target, $this->compiler->connection->context->modes->release), $target->kind->value, $this->label($node));
    }

    /**
     * Reads the name of a projected conversion from its resolved query field.
     * Nested conversions have no item name unless a call argument supplies one.
     */
    public function label(Scalar $node): string
    {
        $walker = new \MySqlMemory\Evaluation\Compile\Walker();
        $bare = new \MySqlMemory\Typing\Quietness();
        foreach ($walker->find($this->compiler->planner->statement, \SqlSemantics\Statement\Query::class) as $query) {
            if (!$this->compiler->facts->covers($query)) {
                continue;
            }
            foreach ($this->compiler->facts->query($query)->fields() ?? [] as $field) {
                if ($field->expression !== null && $bare->bare($field->expression) === $node) {
                    return $field->name->value ?? '(null)';
                }
            }
        }
        foreach ($walker->find($this->compiler->planner->statement, \SqlSemantics\Platform\MySql\Statement\Call\CallArgument::class) as $argument) {
            if ($bare->bare($argument->expression) === $node) {
                $profile = new \SqlSemantics\Contract\LanguageProfile($this->compiler->settings->release());
                $name = (new \SqlSemantics\Platform\MySql\Rules\Query\ItemNaming($profile))->name(new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($argument->expression, $argument->alias));
                return $name instanceof \SqlSemantics\Statement\Identifier\Name ? $name->value : '(null)';
            }
        }
        return '(null)';
    }

    /**
     * Compiles CAST(value AT TIME ZONE zone AS DATETIME): a TIMESTAMP value as a DATETIME in UTC.
     *
     * The value is a TIMESTAMP column or NULL, else the cast is an error; the zone is 'UTC' or
     * '+00:00', spelled so, else it is an unknown time zone. A precision above 6 is refused before
     * (Problems).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is no TIMESTAMP, or the zone is not UTC
     */
    public function atTimeZone(AtTimeZone $node, Scope $scope): Evaluable
    {
        $operand = $this->timestamp($node->operand, $scope);
        if ($this->compiler->connection->context->timeZone($node->zone->value) === null) {
            throw DataError::UnknownTimeZone->error($node->zone->value);
        }

        return new Zoned($operand, $this->compiler->domain($node));
    }

    /**
     * Resolves a conversion input before checking its following time-zone operand.
     *
     * @throws \MySqlMemory\Error\SqlError When the resolved input cannot supply a TIMESTAMP
     */
    public function timestamp(Scalar $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node, $scope);
        $domain = $operand->domain();
        if ($domain->field !== Field::Timestamp && $domain->kind !== Kind::Null && (new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\TimeZoneOperands())->literal($node) !== true) {
            throw DataError::TimeZoneCast->error();
        }
        return $operand;
    }
}
