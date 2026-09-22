<?php

declare(strict_types=1);

namespace SqlCatalog\Extension;

use SqlCatalog\Sql\StatementKind;

/**
 * One call an extension recognises as reaching the database.
 *
 * The parameter indexes say where the statement text and the bound values sit
 * in the argument list, which is all the analyzer needs in order to treat a
 * framework's own API the same way it treats a raw driver call.
 *
 * @visibility root
 */
final class SinkSpec
{
    /**
     * @param string $id The identifier reported on matched call sites
     * @param SinkCallKind $callKind How the call is written
     * @param string|null $receiverType The class the call is made on, or null for a free function
     * @param string $name The method or function name
     * @param SinkRole $role What the call does with the statement
     * @param int|null $sqlParameter Where the statement text sits in the argument list
     * @param int|null $valuesParameter Where an array of bound values sits
     * @param int|null $valuesFrom Where variadic bound values start
     * @param int|null $nameParameter Where the name of a single bound parameter sits
     * @param int|null $valueParameter Where the value of a single bound parameter sits
     * @param StatementKind|null $kind The statement kind the call implies, when it implies one
     * @param string|null $handleType The class a preparing call returns, so later binds find the statement
     */
    public function __construct(
        public readonly string $id,
        public readonly SinkCallKind $callKind,
        public readonly ?string $receiverType,
        public readonly string $name,
        public readonly SinkRole $role,
        public readonly ?int $sqlParameter = null,
        public readonly ?int $valuesParameter = null,
        public readonly ?int $valuesFrom = null,
        public readonly ?int $nameParameter = null,
        public readonly ?int $valueParameter = null,
        public readonly ?StatementKind $kind = null,
        public readonly ?string $handleType = null,
    ) {
    }

    /**
     * Whether the call is written with the given name, ignoring case.
     */
    public function matchesName(string $name): bool
    {
        return strtolower(ltrim($name, '\\')) === strtolower(ltrim($this->name, '\\'));
    }
}
