<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Routine;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Declaration\ParameterDeclaration;
use SqlSemantics\Model\Definition\Routine\Declaration\ParameterInvariant;
use SqlSemantics\Model\Definition\Routine\Option\OptionInvariant;
use SqlSemantics\Model\Definition\Routine\Option\RoutineOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Rules shared by the PostgreSQL routine definition statements.
 * @visibility SqlSemantics
 */
final class RoutineInvariant
{
    /**
     * Checks the dialect, the routine name, the parameter list, and the attribute list of a new routine.
     * @param list<ParameterDeclaration> $parameters
     * @param list<RoutineOption|RoutineSecurity> $options
     * @throws InvalidStructure
     */
    public static function definition(Origin $origin, QualifiedName $name, array $parameters, array $options, bool $procedure): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Routine definitions require PostgreSQL.');
        }
        CatalogInvariant::name($name, 3);
        ParameterInvariant::parameters($parameters, $procedure);
        OptionInvariant::options($options, $procedure);
    }
}
