<?php

declare(strict_types=1);

namespace Spec\Specification;

use LogicException;
use Spec\Subject\FamilyDefinition;
use Spec\Subject\FamilyRequest;
use Spec\Subject\SqlWitness;
use SqlFaker\Contract\Runtime;

abstract class AbstractDialectSpecification implements DialectSpecification
{
    /** @var array<string, FamilyDefinition>|null */
    private ?array $familyMap = null;

    /**
     * @return list<FamilyDefinition>
     */
    final public function familyCatalog(): array
    {
        return array_values($this->familyMap());
    }

    final public function family(string $familyId): FamilyDefinition
    {
        $family = $this->familyMap()[$familyId] ?? null;
        if ($family === null) {
            throw new LogicException(sprintf('Unknown family: %s', $familyId));
        }

        return $family;
    }

    /**
     * @return array<string, FamilyDefinition>
     */
    final protected function familyMap(): array
    {
        if ($this->familyMap !== null) {
            return $this->familyMap;
        }

        $families = [];
        foreach ($this->buildFamilies() as $family) {
            $families[$family->id] = $family;
        }

        return $this->familyMap = $families;
    }

    /**
     * @return list<FamilyDefinition>
     */
    abstract protected function buildFamilies(): array;

    /**
     * @param array<string, scalar> $parameters
     * @param callable(Runtime, array<string, scalar>, int): string $render
     * @param null|callable(string, array<string, scalar>): bool $predicate
     * @param null|callable(string, array<string, scalar>): array<string, scalar> $properties
     */
    protected function searchWitness(
        Runtime $runtime,
        string $familyId,
        array $parameters,
        callable $render,
        ?callable $predicate = null,
        ?callable $properties = null,
        int $maxAttempts = 512,
    ): SqlWitness {
        for ($seed = 1; $seed <= $maxAttempts; $seed++) {
            $sql = $render($runtime, $parameters, $seed);
            if ($predicate !== null && !$predicate($sql, $parameters)) {
                continue;
            }

            return new SqlWitness(
                $familyId,
                $seed,
                $sql,
                $parameters,
                $properties !== null ? $properties($sql, $parameters) : [],
            );
        }

        throw new LogicException(sprintf('No witness found for family %s after %d attempts.', $familyId, $maxAttempts));
    }

    protected function assertFamilyParameters(FamilyRequest $request): FamilyDefinition
    {
        $family = $this->family($request->familyId);
        $unknown = array_diff(array_keys($request->parameters), $family->parameterNames);
        if ($unknown !== []) {
            throw new LogicException(sprintf(
                'Unknown parameters for family %s: %s',
                $request->familyId,
                implode(', ', $unknown),
            ));
        }

        foreach ($family->parameterNames as $parameterName) {
            if (!array_key_exists($parameterName, $request->parameters)) {
                throw new LogicException(sprintf('Missing required parameter %s for family %s.', $parameterName, $request->familyId));
            }
        }

        return $family;
    }
}
