<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Model;

use Deriver\ControlFlow\Parameter;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Context;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;

/**
 * Applies PHP 8.3's deprecated null coercion for non-nullable internal scalar parameters.
 * @visibility root
 */
final class NativeArguments
{
    /**
     * @param Context $context Target diagnostic collector
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Converts explicitly supplied null without applying this internal rule to source signatures.
     * @param Parameter $parameter Selected formal parameter
     * @param PassedArgument $argument Normalized actual
     * @param SourceRef $source Model invocation origin
     * @return PassedArgument Native argument after any deprecated scalar conversion
     */
    public function coerce(Parameter $parameter, PassedArgument $argument, SourceRef $source): PassedArgument
    {
        $value = $argument->value;
        $types = explode('|', $parameter->type);
        if ($value->kind !== 'constant' || $value->literal !== null || in_array('null', $types, true)) {
            return $argument;
        }
        foreach (['string' => '', 'int' => 0, 'float' => 0.0, 'bool' => false] as $type => $replacement) {
            if (in_array($type, $types, true)) {
                $this->context->frontier('PHP_WARNING', $source, 'deprecated-null-to-internal-scalar', [$value]);
                return new PassedArgument(Term::constant($replacement, $value->isSecret()), $argument->name, $argument->location, $argument->elements, $argument->writable);
            }
        }
        return $argument;
    }
}
