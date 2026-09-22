<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Functions;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Validates a function declaration against its containing schema snapshot.
 * @visibility SqlSemantics
 */
final class Registration
{
    /**
     * @throws InvalidStructure
     */
    public static function check(Dialect $dialect, FunctionSignature $function): void
    {
        foreach ($function->parameters ?? [] as $parameter) {
            if ($parameter->dialect !== $dialect) {
                throw new InvalidStructure('A function must use the schema dialect.');
            }
        }
        if ($function->returnType instanceof TypeDescriptor && $function->returnType->dialect !== $dialect) {
            throw new InvalidStructure('A function must use the schema dialect.');
        }
    }
}
