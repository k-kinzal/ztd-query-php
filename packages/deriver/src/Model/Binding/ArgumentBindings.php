<?php

declare(strict_types=1);

namespace Deriver\Model\Binding;

use Deriver\Model\Signature\Signature;
use Deriver\Value\Term;

/**
 * Signature-normalized abstract inputs with an explicit distinction between preparation and evaluated calls.
 * @visibility public
 * @example Normalizing formal names
 *     $bindings = \Deriver\Model\Binding\ArgumentBindings::formal(new \Deriver\Model\Signature\Signature([new \Deriver\Model\Signature\Parameter('id')]));
 *     $bindings->arguments['id']->name // => 'id'
 */
final class ArgumentBindings
{
    /**
     * @param array<string, BoundArgument> $arguments Bindings indexed by declared parameter name
     * @param bool $evaluated Whether actual argument expressions have completed
     * @param string|null $error Certain argument-order error, if any
     * @param Term|null $remainder Unknown unpack residual, never silently treated as empty
     */
    public function __construct(public readonly array $arguments = [], public readonly bool $evaluated = false, public readonly ?string $error = null, public readonly ?Term $remainder = null)
    {
    }

    /**
     * Exposes symbolic handles while selecting a call signature before argument evaluation.
     * @param Signature $signature Candidate callable contract
     * @return self Parameterized inputs; concrete branching belongs in the semantic plan
     */
    public static function formal(Signature $signature): self
    {
        $arguments = [];
        foreach ($signature->parameters as $parameter) {
            $arguments[$parameter->name] = new BoundArgument($parameter->name, Term::parameter($parameter->name, $parameter->variadic ? 'array' : $parameter->type), $parameter->byReference ? LocationRef::parameter($parameter->name) : null, variadic: $parameter->variadic);
        }
        return new self($arguments);
    }
}
