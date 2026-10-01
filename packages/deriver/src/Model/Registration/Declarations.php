<?php

declare(strict_types=1);

namespace Deriver\Model\Registration;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Program;
use Deriver\Model\Metadata\ClassMetadata;
use Deriver\Model\Metadata\DeclarationLookup;
use Deriver\Model\Metadata\PropertyMetadata;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Value\Term;
use Override;

/**
 * Projects source declarations into immutable metadata without exposing graphs or syntax.
 * @visibility root
 */
final class Declarations implements DeclarationLookup
{
    /**
     * @param Program $program Captured declaration world
     */
    public function __construct(private readonly Program $program)
    {
    }

    /**
     * @return list<string> Declared callable identities
     */
    #[Override]
    public function symbols(): array
    {
        $this->program->callOwners('*');
        return $this->program->symbols();
    }

    /**
     * @param string $symbol Callable identity
     * @return Signature|null Source signature
     */
    #[Override]
    public function signature(string $symbol): ?Signature
    {
        $body = $this->program->callable($symbol);
        if ($body === null) {
            return null;
        }
        $parameters = [];
        foreach ($body->parameters as $parameter) {
            $parameters[] = new Parameter($parameter->name, $parameter->type, $parameter->byReference, $parameter->variadic, self::initializer($parameter->default));
        }
        return new Signature($parameters, $body->returnType, $body->allowExtraArguments, $body->byReference);
    }

    /**
     * @param string $name Class name
     * @return ClassMetadata|null Class facts
     */
    #[Override]
    public function class(string $name): ?ClassMetadata
    {
        $class = $this->program->classes()[strtolower(ltrim($name, '\\'))] ?? null;
        if ($class === null) {
            return null;
        }
        $properties = [];
        foreach ($class->properties as $key => $property) {
            $properties[$key] = new PropertyMetadata($property->name, $property->className, $property->type, $property->visibility, $property->static, $property->readonly, self::initializer($property->default));
        }
        return new ClassMetadata($class->name, $class->parent, $class->interfaces, $class->traits, $class->methods, $properties, $class->constants, $class->final, $class->abstract, $class->interface, $class->enum);
    }

    /**
     * Reads only literal initializers; computed defaults remain explicit dependencies.
     * @param CallableGraph|null $graph Captured initializer
     * @return Term|null Literal, unevaluated initializer, or no default
     */
    public static function initializer(?CallableGraph $graph): ?Term
    {
        if ($graph === null) {
            return null;
        }
        $block = $graph->blocks[0] ?? null;
        if ($block !== null && count($graph->blocks) === 1 && count($block->instructions) === 1 && $block->terminator->operand === $block->instructions[0]->result) {
            return $block->instructions[0]->constant ?? Term::opaque('UNEVALUATED_INITIALIZER');
        }
        return Term::opaque('UNEVALUATED_INITIALIZER');
    }
}
