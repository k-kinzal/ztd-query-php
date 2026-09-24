<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Declaration;

use SqlSemantics\Model\Definition\Routine\ParameterMode;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\ArrayStorage;
use SqlSemantics\Type\TypeDescriptor;

/**
 * The rules PostgreSQL applies to the parameter list of a new routine.
 * @visibility SqlSemantics
 */
final class ParameterInvariant
{
    /**
     * Input parameters after a default have defaults, VARIADIC is the last input and an array,
     * a procedure has no output after VARIADIC or after a default, and names are unique except between a pure input and a pure output.
     * @param list<ParameterDeclaration> $parameters
     * @throws InvalidStructure
     */
    public static function parameters(array $parameters, bool $procedure): void
    {
        Collections::objects($parameters, ParameterDeclaration::class);
        $defaults = false;
        $variadic = false;
        foreach ($parameters as $index => $declaration) {
            $parameter = $declaration->parameter;
            if (($declaration->input() && $variadic) || ($procedure && $declaration->output() && $variadic)) {
                throw new InvalidStructure('VARIADIC is the last input parameter, and the last parameter of a procedure.');
            }
            if ($declaration->default === null && $defaults && ($declaration->input() || $procedure)) {
                throw new InvalidStructure('Input parameters after one with a default value have defaults too.');
            }
            $defaults = $defaults || $declaration->default !== null;
            if ($parameter->mode === ParameterMode::Variadic) {
                $variadic = true;
                self::variadic($parameter->type);
            }
            foreach (array_slice($parameters, 0, $index) as $previous) {
                if ($parameter->name !== null && $parameter->name === $previous->parameter->name && self::conflicting($declaration, $previous)) {
                    throw new InvalidStructure('A parameter name is used once.');
                }
            }
        }
    }

    /**
     * A pure input parameter and a pure output parameter may share a name.
     */
    public static function conflicting(ParameterDeclaration $left, ParameterDeclaration $right): bool
    {
        $pureInput = static fn (ParameterDeclaration $declaration): bool => !$declaration->output();
        $pureOutput = static fn (ParameterDeclaration $declaration): bool => $declaration->parameter->mode === ParameterMode::Output;
        return !(($pureInput($left) && $pureOutput($right)) || ($pureOutput($left) && $pureInput($right)));
    }

    /**
     * A VARIADIC parameter collects its arguments into an array, or accepts any type.
     * @throws InvalidStructure
     */
    public static function variadic(TypeDescriptor|\SqlSemantics\Model\Definition\Routine\ColumnTypeReference $type): void
    {
        if ($type instanceof TypeDescriptor && !$type->identity instanceof ArrayStorage && !in_array($type->name, ['any', 'anyarray', 'anycompatiblearray'], true)) {
            throw new InvalidStructure('A VARIADIC parameter is an array.');
        }
    }

    /**
     * A RETURNS TABLE function takes only input parameters and names each result column once.
     * @param list<ParameterDeclaration> $parameters
     * @param list<ResultColumn> $columns
     * @throws InvalidStructure
     */
    public static function table(array $parameters, array $columns): void
    {
        Collections::objects($columns, ResultColumn::class);
        foreach ($parameters as $declaration) {
            if ($declaration->output()) {
                throw new InvalidStructure('RETURNS TABLE takes no OUT or INOUT parameters.');
            }
        }
        $names = array_map(static fn (ResultColumn $column): string => $column->name, $columns);
        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidStructure('A result column name is used once.');
        }
    }
}
