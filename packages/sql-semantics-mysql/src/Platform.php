<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use InvalidArgumentException;
use SqlParser\Lexer\ParameterSyntax;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\SqlMode;
use SqlParser\Parser\Node;
use SqlParser\Parser\SqlParser;
use SqlParser\Resource\VersionRegistry;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Rules\LeafKeys;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Wires the MySQL grammar releases, lowering rules and spelling rules into the analysis.
 *
 * Rule: MYSQL-PROFILE-001. A profile is one shipped grammar release, the
 * five lexical sql_mode settings, and the parameter style. The installed
 * grammar and keyword artifacts must have the digests the release pins.
 *
 * Rule: MYSQL-CONTEXT-001. An unqualified table name is searched in one
 * database, the current database of the session; a context names it, and
 * `mysql` is not assumed: without a search path the database is the name
 * `(current)`, which no qualified name written in SQL can equal by accident
 * of spelling only if the caller never declares it, so callers that use
 * qualified names give the real name. Table and database names are compared
 * exactly, which is the server setting lower_case_table_names=0, the default
 * on Unix; the setting is server configuration, the language profile has no
 * field for it, and a server that runs with 1 or 2 needs the core profile to
 * record it (see the family plan). Column names are compared without regard
 * to ASCII letter case. The server folds every letter by the simple case
 * mapping of its system character set, so two column names that differ only
 * in the case of a letter outside ASCII are one name to the server and two
 * names here; for names whose letters outside ASCII are written identically
 * the comparison is exact.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/identifier-case-sensitivity.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Platform implements \SqlSemantics\Contract\Platform
{
    /**
     * @var array<string, SqlParser>
     */
    private array $parsers = [];

    /**
     * Fixes the profile of a shipped MySQL release under a session mode.
     *
     * The release is named as the parser tags it (`mysql-8.4.7`) or by its number alone (`8.4.7`);
     * without a release the default of the installed parser is used.
     *
     * @throws InvalidArgumentException When the release is not shipped, the mode belongs to another database, or the installed grammar artifacts differ from the pinned ones
     */
    public function profile(?string $version, ?\SqlSemantics\Contract\Mode $mode, ParameterStyle $parameters): LanguageProfile
    {
        if ($mode !== null && !$mode instanceof Mode) {
            throw new InvalidArgumentException('MySQL reads SQL under a MySQL session mode; ' . $mode::class . ' given.');
        }
        $registry = new VersionRegistry();
        $name = $version ?? $registry->resolve('mysql')->name;
        $release = GrammarRelease::tryFrom(str_starts_with($name, 'mysql-') ? $name : 'mysql-' . $name);
        if ($release === null || $release->database() !== 'mysql' || !in_array($release->value, $registry->names('mysql'), true)) {
            throw new InvalidArgumentException('No semantic profile exists for the selected grammar release.');
        }
        $artifact = $registry->resolve('mysql', $release->value);
        if (hash_file('sha256', $artifact->tablePath) !== $release->grammarDigest() || hash_file('sha256', $artifact->keywordPath) !== $release->keywordDigest()) {
            throw new InvalidArgumentException('The installed grammar artifacts do not match the fixed semantic profile.');
        }

        return new LanguageProfile($release, new LexicalSettings($mode?->toString() ?? ''), $parameters);
    }

    /**
     * Answers the parser of a profile, created once per profile.
     */
    public function parser(LanguageProfile $profile): SqlParser
    {
        $lexical = $profile->lexical;
        $mode = new SqlMode($lexical->ansiQuotes, $lexical->pipesAsConcat, $lexical->highNotPrecedence, $lexical->noBackslashEscapes, $lexical->ignoreSpace);
        $key = $profile->grammar->value . '|' . (new Mode($lexical->ansiQuotes, $lexical->pipesAsConcat, $lexical->highNotPrecedence, $lexical->noBackslashEscapes, $lexical->ignoreSpace))->toString() . '|' . $profile->parameters->value;

        return $this->parsers[$key] ??= new MySqlParser($profile->grammar->value, $mode, new VersionRegistry(), $profile->parameters === ParameterStyle::Named ? ParameterSyntax::Named : ParameterSyntax::Native);
    }

    /**
     * Answers the productions of the release.
     */
    public function productions(LanguageProfile $profile): Productions
    {
        return Productions::load(dirname(__DIR__) . '/resources/productions/' . $profile->grammar->value . '.php');
    }

    /**
     * Lowers a parse tree into its statements.
     */
    public function lower(Node $tree, LanguageProfile $profile, Leaves $leaves): array
    {
        return (new Lowering($this->productions($profile), $leaves, $profile))->statements($tree);
    }

    /**
     * Answers the name codec of the release.
     */
    public function codec(LanguageProfile $profile): Codec
    {
        return new Codec($profile->grammar);
    }

    /**
     * Answers the token comparison keys under the lexical settings of the profile.
     */
    public function leafKeys(LanguageProfile $profile): LeafKeys
    {
        return new LeafKeys($profile->lexical);
    }

    /**
     * Creates a context: one current database, exact table and database names, column names without regard to ASCII case.
     *
     * @throws InvalidArgumentException When the path names more than the current database
     */
    public function context(LanguageProfile $profile, ?array $searchPath, array $tables, bool $complete): AnalysisContext
    {
        if ($searchPath !== null && count($searchPath) !== 1) {
            throw new InvalidArgumentException('MySQL searches an unqualified table name in the current database only.');
        }

        return new AnalysisContext($profile, [new Name($searchPath[0] ?? SessionDatabase::UNNAMED)], $tables, $complete, Comparison::Sensitive, Comparison::AsciiInsensitive);
    }

    /**
     * Answers the namespace of the MySQL statement values.
     */
    public function statementNamespace(): string
    {
        return 'SqlSemantics\\Platform\\MySql\\Statement\\';
    }
}
