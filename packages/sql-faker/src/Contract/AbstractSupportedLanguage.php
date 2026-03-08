<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

use LogicException;

/**
 * Shared base for dialect-specific supported language contracts.
 *
 * It provides lazy caching for grammar snapshots and family catalogs, validates
 * family parameters, and offers the bounded witness search loop used by the
 * concrete dialect subjects.
 */
abstract class AbstractSupportedLanguage implements SupportedLanguage
{
    /** @var array<string, FamilyDefinition>|null */
    private ?array $familyMap = null;

    private ?GrammarSnapshot $snapshot = null;

    /**
     * Lazily builds and caches the grammar snapshot exposed by this subject.
     */
    final public function grammarSnapshot(): GrammarSnapshot
    {
        return $this->snapshot ??= $this->buildGrammarSnapshot();
    }

    /**
     * Returns the family catalog as a stable list suitable for reporting and iteration.
     *
     * @return list<FamilyDefinition>
     */
    final public function familyCatalog(): array
    {
        return array_values($this->familyMap());
    }

    /**
     * Resolves one family definition by id and fails fast when the subject does not expose it.
     */
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

    abstract protected function buildGrammarSnapshot(): GrammarSnapshot;

    abstract protected function seed(int $seed): void;

    /**
     * @param array<string, scalar> $parameters
     * @param callable(array<string, scalar>): string $render
     * @param null|callable(string, array<string, scalar>): bool $predicate
     * @param null|callable(string, array<string, scalar>): array<string, scalar> $properties
     */
    protected function searchWitness(
        string $familyId,
        array $parameters,
        callable $render,
        ?callable $predicate = null,
        ?callable $properties = null,
        int $maxAttempts = 512,
    ): SqlWitness {
        for ($seed = 1; $seed <= $maxAttempts; $seed++) {
            $this->seed($seed);
            $sql = $render($parameters);
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
