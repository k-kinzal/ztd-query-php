<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Call\Native;

use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\BasicBlock;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\IR\Parameter;
use Deriver\Internal\IR\Program;
use Deriver\Internal\IR\Terminator;
use Deriver\Internal\Solver\Call\Dispatch;
use Deriver\Value\Term;

/**
 * Captures PHP 8.3 throwable method signatures without loading host classes.
 * @visibility root
 */
final class Signatures
{
    /**
     * @param Program $program Captured declaration hierarchy
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Selects the native constructor family inherited by a known class.
     * @param string $class Requested class
     * @return string Native constructor owner or empty when there is none
     */
    public function family(string $class): string
    {
        $dispatch = new Dispatch($this->program);
        foreach (['ErrorException', 'Exception', 'Error'] as $candidate) {
            if ($dispatch->subtype($class, $candidate)) {
                return $candidate;
            }
        }
        return '';
    }

    /**
     * Describes the names, types and defaults of the selected native constructor.
     * @param string $family Native constructor family
     * @return array<string, array{string, scalar|null}> Ordered parameter definitions
     */
    public function parameters(string $family): array
    {
        $parameters = ['message' => ['string', ''], 'code' => ['int', 0]];
        if ($family === 'ErrorException') {
            $parameters += ['severity' => ['int', 1], 'filename' => ['string|null', null], 'line' => ['int|null', null]];
        }
        $parameters['previous'] = ['Throwable|null', null];
        return $parameters;
    }

    /**
     * Builds a signature that uses the ordinary argument validation machinery.
     * @param string $family Native constructor family
     * @param string $method Canonical lowercase method name
     * @param SourceRef $source Call-site provenance
     * @return CallableIR Signature with immutable default expressions
     */
    public function graph(string $family, string $method, SourceRef $source): CallableIR
    {
        $parameters = [];
        foreach ($method === '__construct' ? $this->parameters($family) : [] as $name => [$type, $value]) {
            $constant = new Instruction('native-default:' . $name, 'constant', $source, 'value', constant: Term::constant($value));
            $default = new CallableIR('native-default:' . $name, [], [new BasicBlock(0, [$constant], new Terminator('return', 'value'))], $source);
            $parameters[] = new Parameter($name, $type, default: $default);
        }
        return new CallableIR($family . '::' . $method, $parameters, [], $source, className: $family, allowExtraArguments: false);
    }
}
