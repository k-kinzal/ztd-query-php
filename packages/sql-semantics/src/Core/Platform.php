<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use SqlParser\Parser\SqlParser;
use SqlSemantics\Core\Policy\NameRules;
use SqlSemantics\Core\Policy\QueryRules;
use SqlSemantics\Core\Policy\SchemaRules;
use SqlSemantics\Core\Policy\TypeRules;

/**
 * The concrete parsing and semantic behavior required by core binding.
 *
 * @visibility SqlSemantics
 */
interface Platform
{
    /**
     * Configures a parser for the requested release.
     */
    public function parser(?string $version = null): SqlParser;

    /**
     * Supplies statement construction data for the resolved grammar release.
     */
    public function values(string $version): Analysis\ValueReader;

    /**
     * Supplies the unqualified declaration namespace.
     */
    public function defaultSchema(): string;

    /**
     * @return array{string, string} Root and statement grammar names
     */
    public function statementNames(): array;

    /**
     * Supplies the configured grammar vocabulary.
     */
    public function syntax(): Policy\SyntaxRules;

    /**
     * Supplies identifier semantics.
     */
    public function names(): NameRules;

    /**
     * Supplies scalar type semantics.
     */
    public function types(): TypeRules;

    /**
     * Supplies declaration syntax and constraint semantics.
     */
    public function schema(): SchemaRules;

    /**
     * Supplies query syntax interpretation.
     */
    public function query(): QueryRules;
}
