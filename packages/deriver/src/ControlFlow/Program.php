<?php

declare(strict_types=1);

namespace Deriver\ControlFlow;

use Deriver\Result\Frontier;

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
     * @return array<string, ClassDeclaration> Classes indexed case-insensitively
     */
    public function classes(): array;

    /**
     * Returns known callable identities in deterministic order.
     * @return list<string> Callable identities
     */
    public function symbols(): array;

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
}
