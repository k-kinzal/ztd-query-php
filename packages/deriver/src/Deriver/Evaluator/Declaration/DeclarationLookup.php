<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Declaration;

/**
 * Read-only declaration facts captured from sources and explicit stubs.
 * @visibility root
 */
interface DeclarationLookup
{
    /**
     * Lists callable identities, including scripts and lexical closures.
     * The list is a declaration inventory, not a claim that all entries execute.
     * @return list<string> Deterministically sorted identities
     */
    public function symbols(): array;

    /**
     * Reads declared names, types, reference modes, variadics, and defaults.
     * @param string $symbol Callable identity
     * @return Signature|null Captured signature or absent declaration
     */
    public function signature(string $symbol): ?Signature;

    /**
     * Reads class metadata without instantiation, reflection, or autoloading.
     * @param string $name Class name, matched case-insensitively
     * @return ClassMetadata|null Captured declaration
     */
    public function class(string $name): ?ClassMetadata;
}
