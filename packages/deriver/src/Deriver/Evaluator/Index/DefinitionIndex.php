<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Index;

use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Program;

/**
 * Snapshot-local definition, caller, and declared-property write indexes.
 * Building an index never derives instruction values.
 * @visibility Deriver
 * @visibility parent
 * @visibility Deriver\Deriver
 * @visibility Deriver\Deriver
 * @visibility Deriver\Deriver
 */
final class DefinitionIndex
{
    /**
     * @var array<string, BodyIndex>
     */
    private array $graphs = [];
    /**
     * @var array<string, list<array{BodyIndex, Instruction}>>
     */
    private array $callers = [];
    /**
     * @var array<string, list<array{BodyIndex, Instruction}>>
     */
    private array $writes = [];

    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Retrieves indexed semantics without evaluating source instructions.
     */
    public function graph(string $symbol): ?BodyIndex
    {
        $key = (new CallableIdentity())->key($symbol);
        $body = $this->graphs[$key] ?? null;
        if ($body !== null) {
            return $body;
        }
        $source = $this->program->callable($symbol);
        return $source === null ? null : ($this->graphs[$key] = new BodyIndex($source));
    }

    /**
     * @return list<array{BodyIndex, Instruction}>
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
            if ($graph === null) {
                continue;
            }
            foreach ($graph->definitions as $instruction) {
                $target = $this->target($graph, $instruction);
                $dynamicMethod = $instruction->operation === 'invoke-method' && str_contains($symbol, '::') && ($this->literal($graph, $instruction->operands[1]) === '' || strcasecmp($this->literal($graph, $instruction->operands[1]), $selector) === 0) && $this->compatible($target, $symbol);
                if ($target !== '' && (new CallableIdentity())->key($target) === $key || $dynamicMethod) {
                    $found[] = [$graph, $instruction];
                }
            }
        }
        return $this->callers[$key] = $found;
    }

    /**
     * Reports whether a statically typed call site can still dispatch to the requested method.
     * A receiver whose complete class hierarchy neither contains nor descends from the requested class
     * cannot reach it, so such sites are not derived; unknown or incomplete receivers remain candidates.
     * @param string $target Statically discovered target, or the empty string
     * @param string $symbol Requested `Class::method`
     */
    public function compatible(string $target, string $symbol): bool
    {
        if ($target === '' || !str_contains($target, '::')) {
            return true;
        }
        $class = explode('::', $target)[0];
        $wanted = explode('::', $symbol)[0];
        $hierarchy = new \Deriver\Deriver\Lowerer\Hierarchy\Dispatch($this->program);
        if (!isset($this->program->classes()[strtolower($class)]) || !$hierarchy->complete($class)) {
            return true;
        }
        return $hierarchy->subtype($class, $wanted) || $hierarchy->subtype($wanted, $class);
    }

    /**
     * Discovers a declaration target; receiver values must still select instance dispatch.
     */
    public function target(BodyIndex $graph, Instruction $instruction): string
    {
        $operation = $instruction->operation;
        if ($operation === 'invoke') {
            $name = $this->literal($graph, $instruction->operands[0] ?? '');
            if ($name !== '' && $this->program->hasSymbol($name)) {
                return $name;
            }
            $fallback = (string) ($instruction->attributes['fallback'] ?? '');
            return $fallback !== '' ? $fallback : $name;
        }
        if (!in_array($operation, ['invoke-method', 'invoke-static', 'new'], true)) {
            return '';
        }
        $class = $operation === 'invoke-method' ? $this->receiverClass($this->type($graph, $instruction->operands[0])) : $this->literal($graph, $instruction->operands[0]);
        $class = $this->className($class, $graph->body->className);
        $name = $operation === 'new' ? '__construct' : $this->literal($graph, $instruction->operands[1]);
        return $class === '' || $name === '' ? '' : $this->method($class, $name);
    }

    /**
     * Extracts the one class a receiver type can name; other types leave the dispatch unresolved.
     * A nullable or null-union type keeps its class, since the call throws on null rather than dispatching
     * elsewhere; `mixed`, `object`, scalar and union types do not identify a class.
     * @param string $type Declared or inferred receiver type
     * @return string Class name, or the empty string
     */
    public function receiverClass(string $type): string
    {
        $members = [];
        foreach (explode('|', ltrim($type, '?')) as $member) {
            $member = ltrim(trim($member), '\\');
            if (strcasecmp($member, 'null') === 0) {
                continue;
            }
            $members[] = $member;
        }
        if (count($members) !== 1) {
            return '';
        }
        if (isset($this->program->classes()[strtolower($members[0])])) {
            return $members[0];
        }
        $builtin = ['mixed', 'object', 'string', 'int', 'float', 'bool', 'array', 'callable', 'iterable', 'void', 'never', 'false', 'true', 'resource', 'scalar', 'numeric', 'integer', 'boolean', 'double'];
        if (in_array(strtolower($members[0]), $builtin, true) || preg_match('/\A[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\\]*\z/', $members[0]) !== 1) {
            return '';
        }
        return $members[0];
    }

    /**
     * Reads an indexed literal name without evaluating an expression.
     */
    public function literal(BodyIndex $graph, string $register): string
    {
        $instruction = $graph->definitions[$register] ?? null;
        return $instruction?->operation === 'constant' && $instruction->constant?->kind === \Deriver\Deriver\Lowerer\Graph\LiteralKind::Constant && is_string($instruction->constant->literal) ? $instruction->constant->literal : '';
    }

    /**
     * Resolves lexical class keywords against captured inheritance.
     */
    public function className(string $class, string $scope, string $calledClass = ''): string
    {
        return match (strtolower($class)) {
            'self' => $scope,
            'static' => $calledClass !== '' ? $calledClass : $scope,
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
    public function type(BodyIndex $graph, string $register, int $depth = 32): string
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
    public function declaredProperty(BodyIndex $graph, Instruction $address): ?PropertyDeclaration
    {
        if (!in_array($address->operation, ['field-address', 'static-address'], true)) {
            return null;
        }
        $class = $address->operation === 'field-address' ? $this->type($graph, $address->operands[0]) : $this->className($this->literal($graph, $address->operands[0]), $graph->body->className);
        return $this->property($class, $this->literal($graph, $address->operands[1]));
    }

    /**
     * @return list<array{BodyIndex, Instruction}>
     */
    public function writes(PropertyDeclaration $property): array
    {
        $key = $property->className . '::$' . $property->name;
        if (!isset($this->writes[$key])) {
            $this->writes[$key] = [];
            foreach ($this->program->propertyOwners($property->name) as $symbol) {
                $graph = $this->graph($symbol);
                if ($graph === null) {
                    continue;
                }
                foreach ($graph->definitions as $write) {
                    $address = $graph->root($write);
                    if (!BodyIndex::writes($write) || $address === null) {
                        continue;
                    }
                    $slot = $this->declaredProperty($graph, $address);
                    if ($slot !== null && $slot->className === $property->className && $slot->name === $property->name) {
                        $this->writes[$slot->className . '::$' . $slot->name][] = [$graph, $write];
                    }
                }
            }
        }
        return $this->writes[$property->className . '::$' . $property->name] ?? [];
    }
}
