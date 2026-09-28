<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use InvalidArgumentException;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Core\Policy\NameRules;
use SqlSemantics\Core\Policy\RelationRules;
use SqlSemantics\Core\Policy\SchemaRules;
use SqlSemantics\Core\Policy\TypeRules;

/**
 * The concrete parsing, modeling, and semantic behavior a database package supplies.
 *
 * @visibility SqlSemantics
 */
interface Platform
{
    /**
     * Configures a parser for the requested release, mode, and parameter syntax.
     *
     * @param string|null $version A release tag the package ships, or null for its default
     * @param Mode|null $mode The session settings text is read under, or null for the server's defaults
     * @param Parameters $parameters Which parameter markers are read
     *
     * @throws InvalidArgumentException When the mode does not belong to this database
     */
    public function parser(?string $version = null, ?Mode $mode = null, Parameters $parameters = Parameters::Native): SqlParser;

    /**
     * Supplies statement construction data for the resolved grammar release.
     */
    public function values(string $version): Analysis\ValueReader;

    /**
     * Supplies the composer of this database's values for a language.
     */
    public function builder(Language $language): Builder;

    /**
     * Supplies literal decoding under the language's session settings.
     */
    public function literals(Language $language): Policy\LiteralRules;

    /**
     * Answers the schemas an unqualified table name is read in, in order, for the session's search path or the server's default; an unqualified declaration creates its table in the first.
     *
     * @param SearchPath|null $path The session's search path, or null for the server's default
     * @return non-empty-list<string>
     *
     * @throws InvalidArgumentException When the database cannot search the path
     */
    public function searchPath(?SearchPath $path = null): array;

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
     * Supplies declared type semantics.
     */
    public function types(): TypeRules;

    /**
     * Supplies declaration syntax and constraint semantics.
     */
    public function schema(): SchemaRules;

    /**
     * Supplies where the grammar writes table names and which of them declare, drop, or name tables.
     */
    public function relations(): RelationRules;
}
