<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Transfer;

use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;

/**
 * Keeps property declaration and lexical access facts beside an address register.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class PropertySlot
{
    /**
     * @param Term $receiver Evaluated receiver
     * @param string $name Property spelling
     * @param string $scope Lexical access class
     * @param PropertyDeclaration|null $declaration Declared property, if present
     * @param bool $static Whether syntax uses static storage
     */
    public function __construct(public readonly Term $receiver, public readonly string $name, public readonly string $scope, public readonly ?PropertyDeclaration $declaration, public readonly bool $static = false)
    {
    }
}
