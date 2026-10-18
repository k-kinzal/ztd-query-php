<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Graph;

use Deriver\Deriver\Lowerer\Declaration\Diagnostic as Frontier;

/**
 * Parser-independent access to lazily compiled callable graphs.
 * @visibility root
 */
interface Program
{
    /**
     * Compiles a callable on demand.
     * @param string $symbol Callable identity
     * @return CallableGraph|null Available source graph
     */
    public function callable(string $symbol): ?CallableGraph;

    /**
     * Returns the declaration index without compiling all bodies.
     * @return array<string, \Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration> Classes indexed case-insensitively
     */
    public function classes(): array;

    /**
     * Returns known callable identities in deterministic order.
     * @return list<string> Callable identities
     */
    public function symbols(): array;

    /**
     * Tests membership in symbols() without materializing the complete list.
     */
    public function hasSymbol(string $symbol): bool;

    /**
     * Returns source diagnostics independently of query dependencies.
     * @return list<Frontier> Parse and declaration diagnostics
     */
    public function diagnostics(): array;

    /**
     * Returns the number of materialized graphs.
     * @return int Graph count
     */
    public function graphCount(): int;
    /**
     * Compiles a captured constant initializer on demand.
     * @param string $symbol Case-sensitive constant identity
     * @return CallableGraph|null Available initializer
     */
    public function constant(string $symbol): ?CallableGraph;
    /**
     * Selects possible observation owners before compiling callable bodies.
     * @param string $symbol Function or method selector
     * @return list<string> Deterministic owners
     */
    public function callOwners(string $symbol): array;
    /**
     * @return list<string> Owners whose source may write the named property
     */
    public function propertyOwners(string $name): array;

    /**
     * @return list<string> Lexical owners of a captured closure allocation
     */
    public function closureOwners(string $symbol): array;

    /**
     * @return list<string> Scripts which may define the requested global
     */
    public function globalOwners(string $name): array;

    /**
     * Returns additional conditional implementations of a declaration.
     * @return list<string> Stable declaration identities
     */
    public function variants(string $symbol): array;
}
