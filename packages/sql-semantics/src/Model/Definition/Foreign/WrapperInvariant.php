<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Validates named wrapper definitions and the operand domains of support-function changes.
 * @visibility SqlSemantics
 */
final class WrapperInvariant
{
    /**
     * Requires a PostgreSQL wrapper identity.
     * @throws InvalidStructure
     */
    public static function target(Origin $origin, string $name): void
    {
        if ($origin->dialect !== Dialect::PostgreSql || $name === '') {
            throw new InvalidStructure('A foreign-data wrapper requires PostgreSQL and a nonempty name.');
        }
    }

    /**
     * Accepts a supplied function identity, omission, or an explicit change policy.
     * @throws InvalidStructure
     */
    public static function functionName(QualifiedName|FunctionChange|null $function): void
    {
        if ($function instanceof QualifiedName && (count($function->parts) > 3 || in_array('', $function->parts, true))) {
            throw new InvalidStructure('A support function requires one to three nonempty identifier components.');
        }
    }

    /**
     * @param list<ForeignOption> $options Initial options with unique identifiers
     * @throws InvalidStructure
     */
    public static function options(array $options): void
    {
        Collections::objects($options, ForeignOption::class);
        $names = array_map(static fn (ForeignOption $option): string => $option->name, $options);
        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidStructure('Initial foreign-data wrapper options require unique names.');
        }
    }
}
