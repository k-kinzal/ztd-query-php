<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

use InvalidArgumentException;
use SqlParser\Parser\Node;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Statement;

/**
 * What a database package supplies: its profiles, parser, lowering rules and spelling rules.
 *
 * The three implementations are fixed by the grammar releases. A platform is
 * not an extension point for callers.
 *
 * @visibility SqlSemantics
 */
interface Platform
{
    /**
     * Fixes the language profile of a release, session mode and parameter style.
     *
     * @throws InvalidArgumentException When the release is not shipped, the mode belongs to another database, or the installed grammar artifacts differ from the pinned ones
     */
    public function profile(?string $version, ?Mode $mode, ParameterStyle $parameters): LanguageProfile;

    /**
     * Answers the parser of a profile.
     */
    public function parser(LanguageProfile $profile): SqlParser;

    /**
     * Lowers a parse tree into the statements it contains, recording the operand leaves it creates.
     *
     * @param \SqlSemantics\Construction\Origins|null $origins The optional recorder for original input locations
     * @return list<Statement>
     */
    public function lower(Node $tree, LanguageProfile $profile, Leaves $leaves, ?\SqlSemantics\Construction\Origins $origins = null): array;

    /**
     * Answers the grammar productions of a profile's release.
     */
    public function productions(LanguageProfile $profile): Productions;

    /**
     * Answers the name codec of a profile.
     */
    public function codec(LanguageProfile $profile): Codec;

    /**
     * Answers the token comparison keys of a profile.
     */
    public function leafKeys(LanguageProfile $profile): LeafKeys;

    /**
     * Creates the declaration context of a profile with the name rules of the database.
     *
     * @param list<string>|null $searchPath The schemas an unqualified relation name is searched in, or null for the default of the database
     * @param list<Table> $tables The relation declarations
     * @param bool $complete Whether the declarations enumerate every relation
     *
     * @throws InvalidArgumentException When the database cannot search the path
     */
    public function context(LanguageProfile $profile, ?array $searchPath, array $tables, bool $complete): AnalysisContext;

    /**
     * Answers the namespace of the concrete statement values of this database.
     */
    public function statementNamespace(): string;
}
