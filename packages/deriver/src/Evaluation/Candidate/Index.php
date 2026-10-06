<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Program;
use Deriver\ControlFlow\PropertyDeclaration;

/**
 * Snapshot-local definition, caller, and declared-property write indexes.
 * Building an index never derives instruction values.
 * @visibility root
 */
final class Index
{
    /**
     * @var array<string, Graph>
     */
    private array $graphs = [];
    /**
     * @var array<string, list<array{Graph, Instruction}>>
     */
    private array $callers = [];
    /**
     * @var array<string, list<array{Graph, Instruction}>>|null
     */
    private ?array $writes = null;

    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Retrieves indexed semantics without evaluating source instructions.
     */
    public function graph(string $symbol): ?Graph
    {
        $key = (new CallableIdentity())->key($symbol);
        $body = $this->graphs[$key] ?? null;
        if ($body !== null) {
            return $body;
        }
        $source = $this->program->callable($symbol);
        return $source === null ? null : ($this->graphs[$key] = new Graph($source));
    }

    /**

     * @return list<array{Graph, Instruction}>

     */
    public function callers(string $symbol): array
    {
        $key = (new CallableIdentity())->key($symbol);
        if (isset($this->callers[$key])) {
            return $this->callers[$key];
        }
        $found = [];
        $selector = str_contains($symbol, '::') && !str_ends_with(strtolower($symbol), '::__construct') ? explode('::', $symbol)[1] : $symbol;
        foreach ($this->program->callOwners($selector) as $owner) {
            $graph = $this->graph($owner);
            foreach ($graph->definitions ?? [] as $instruction) {
                $target = $graph === null ? '' : $this->target($graph, $instruction);
                if ($target !== '' && (new CallableIdentity())->key($target) === $key) {
                    $found[] = [$graph, $instruction];
                }
            }
        }
        return $this->callers[$key] = $found;
    }

    /**
     * Discovers a declaration target; receiver values must still select instance dispatch.
     */
    public function target(Graph $graph, Instruction $instruction): string
    {
        $operation = $instruction->operation;
        if ($operation === 'invoke') {
            $name = $this->literal($graph, $instruction->operands[0] ?? '');
            if ($name !== '' && in_array($name, $this->program->symbols(), true)) {
                return $name;
            }
            $fallback = (string) ($instruction->attributes['fallback'] ?? '');
            return $fallback !== '' ? $fallback : $name;
        }
        if (!in_array($operation, ['invoke-method', 'invoke-static', 'new'], true)) {
            return '';
        }
        $class = $operation === 'invoke-method' ? $this->type($graph, $instruction->operands[0]) : $this->literal($graph, $instruction->operands[0]);
        $class = $this->className($class, $graph->body->className);
        $name = $operation === 'new' ? '__construct' : $this->literal($graph, $instruction->operands[1]);
        return $class === '' || $name === '' ? '' : $this->method($class, $name);
    }

    /**
     * Reads an indexed literal name without evaluating an expression.
     */
    public function literal(Graph $graph, string $register): string
    {
        $instruction = $graph->definitions[$register] ?? null;
        return $instruction?->operation === 'constant' && is_string($instruction->constant?->literal) ? $instruction->constant->literal : '';
    }

    /**
     * Resolves lexical class keywords against captured inheritance.
     */
    public function className(string $class, string $scope): string
    {
        return match (strtolower($class)) {
            'self', 'static' => $scope,
            'parent' => $this->program->classes()[strtolower($scope)]->parent ?? '',
            default => $class,
        };
    }

    /**
     * Finds a method in the known inheritance hierarchy.
     */
    public function method(string $class, string $method): string
    {
        $original = $class;
        $seen = [];
        while ($class !== '' && !isset($seen[strtolower($class)])) {
            $seen[strtolower($class)] = true;
            $declaration = $this->program->classes()[strtolower($class)] ?? null;
            if (isset($declaration->methods[strtolower($method)])) {
                return $declaration->methods[strtolower($method)];
            }
            $class = $declaration->parent ?? '';
        }
        return $original . '::' . $method;
    }

    /**
     * Resolves the requested property and its candidate origins.
     */
    public function property(string $class, string $name): ?PropertyDeclaration
    {
        $seen = [];
        while ($class !== '' && !isset($seen[strtolower($class)])) {
            $seen[strtolower($class)] = true;
            $declaration = $this->program->classes()[strtolower($class)] ?? null;
            if (isset($declaration->properties[$name])) {
                return $declaration->properties[$name];
            }
            $class = $declaration->parent ?? '';
        }
        return null;
    }

    /**
     * Reads type information corresponding to the indexed definition.
     */
    public function type(Graph $graph, string $register, int $depth = 32): string
    {
        $instruction = $graph->definitions[$register] ?? null;
        if ($instruction === null || $depth === 0) {
            return '';
        }
        if ($instruction->operation === 'new') {
            return $this->className($this->literal($graph, $instruction->operands[0]), $graph->body->className);
        }
        if (in_array($instruction->operation, ['copy', 'read', 'read-silent'], true)) {
            return $this->type($graph, $instruction->operands[0], $depth - 1);
        }
        if ($instruction->operation !== 'local') {
            return '';
        }
        if ($instruction->name === 'this') {
            return $graph->body->className;
        }
        foreach (array_reverse($graph->definitions) as $write) {
            if ($write->operation === 'write' && $write->source->start < $instruction->source->start && ($graph->definitions[$write->operands[0]]->name ?? '') === $instruction->name) {
                if ($graph->positions[$write->result][0] !== $graph->positions[$instruction->result][0]) {
                    return '';
                }
                return $this->type($graph, $write->operands[1], $depth - 1);
            }
        }
        foreach ($graph->body->parameters as $parameter) {
            if ($parameter->name === $instruction->name) {
                return $parameter->type;
            }
        }
        return '';
    }

    /**
     * Associates a property address with its declaration.
     */
    public function declaredProperty(Graph $graph, Instruction $address): ?PropertyDeclaration
    {
        if (!in_array($address->operation, ['field-address', 'static-address'], true)) {
            return null;
        }
        $class = $address->operation === 'field-address' ? $this->type($graph, $address->operands[0]) : $this->className($this->literal($graph, $address->operands[0]), $graph->body->className);
        return $this->property($class, $this->literal($graph, $address->operands[1]));
    }

    /**

     * @return list<array{Graph, Instruction}>

     */
    public function writes(PropertyDeclaration $property): array
    {
        if ($this->writes === null) {
            $this->writes = [];
            foreach ($this->program->symbols() as $symbol) {
                $graph = $this->graph($symbol);
                if ($graph === null) {
                    continue;
                }
                foreach ($graph->definitions as $write) {
                    $address = Memory\Mutations::root($graph, $write);
                    if (!Memory\Mutations::writes($write) || $address === null) {
                        continue;
                    }
                    $slot = $this->declaredProperty($graph, $address);
                    if ($slot !== null) {
                        $this->writes[$slot->className . '::$' . $slot->name][] = [$graph, $write];
                    }
                }
            }
        }
        return $this->writes[$property->className . '::$' . $property->name] ?? [];
    }
}
