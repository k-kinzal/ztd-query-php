<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use InvalidArgumentException;
use SqlParser\Lexer\ParameterSyntax;
use SqlParser\Parser\Node;
use SqlParser\Parser\SqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\PostgreSql\PostgreSqlVersion;
use SqlParser\Resource\VersionRegistry;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Contract\Mode;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Rules\LeafKeys;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Wires the PostgreSQL grammar releases, lowering rules and spelling rules into the analysis.
 *
 * Rule: PG-PROFILE-001. A profile fixes one shipped grammar release with
 * `standard_conforming_strings = on` and a UTF-8 server encoding; PostgreSQL
 * has no session mode that changes how its grammar reads text otherwise. An
 * unquoted identifier is folded to lower case when it is decoded, so names are
 * compared exactly afterwards. An unqualified relation name is searched in the
 * temporary schema, then `pg_catalog`, then the schemas of the search path,
 * unless the path lists those two itself.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS,
 * https://www.postgresql.org/docs/17/runtime-config-client.html#GUC-SEARCH-PATH.
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
     * Fixes the profile of a shipped PostgreSQL release; PostgreSQL reads SQL under no session mode.
     *
     * @throws InvalidArgumentException When the release is not shipped, a mode is given, or the installed grammar artifacts differ from the pinned ones
     */
    public function profile(?string $version, ?Mode $mode, ParameterStyle $parameters): LanguageProfile
    {
        if ($mode !== null) {
            throw new InvalidArgumentException('PostgreSQL reads SQL under no session mode; ' . $mode::class . ' given.');
        }
        $release = GrammarRelease::tryFrom((new PostgreSqlParser($version))->version());
        if ($release === null || $release->database() !== PostgreSqlVersion::DIALECT) {
            throw new InvalidArgumentException('No semantic profile exists for the selected grammar release.');
        }
        $artifact = (new VersionRegistry())->resolve(PostgreSqlVersion::DIALECT, $release->value);
        if (hash_file('sha256', $artifact->tablePath) !== $release->grammarDigest() || hash_file('sha256', $artifact->keywordPath) !== $release->keywordDigest()) {
            throw new InvalidArgumentException('The installed grammar artifacts do not match the fixed semantic profile.');
        }

        return new LanguageProfile($release, new LexicalSettings(), $parameters);
    }

    /**
     * Answers the parser of a profile, created once per release and parameter style.
     */
    public function parser(LanguageProfile $profile): SqlParser
    {
        $key = $profile->grammar->value . '/' . $profile->parameters->value;

        return $this->parsers[$key] ??= new PostgreSqlParser(
            $profile->grammar->value,
            parameters: $profile->parameters === ParameterStyle::Named ? ParameterSyntax::Named : ParameterSyntax::Native,
        );
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
    public function lower(Node $tree, LanguageProfile $profile, Leaves $leaves, ?\SqlSemantics\Construction\Origins $origins = null): array
    {
        return (new Lowering($this->productions($profile), $leaves, $profile->grammar))->statements($tree);
    }

    /**
     * Answers the name codec.
     */
    public function codec(LanguageProfile $profile): Codec
    {
        return new Codec($profile->grammar);
    }

    /**
     * Answers the token comparison keys.
     */
    public function leafKeys(LanguageProfile $profile): LeafKeys
    {
        return new LeafKeys();
    }

    /**
     * Creates a context: the temporary schema and `pg_catalog` are searched before the path unless the path names them, and names compare exactly.
     */
    public function context(LanguageProfile $profile, ?array $searchPath, array $tables, bool $complete): AnalysisContext
    {
        $schemas = $searchPath ?? ['public'];
        $path = [];
        foreach (['pg_temp', 'pg_catalog'] as $implicit) {
            if (!in_array($implicit, $schemas, true)) {
                $path[] = new Name($implicit);
            }
        }
        $first = null;
        foreach ($schemas as $schema) {
            $name = new Name($schema);
            $path[] = $name;
            if ($first === null && $schema !== 'pg_temp' && $schema !== 'pg_catalog') {
                $first = $name;
            }
        }

        return new AnalysisContext($profile, $path, $tables, $complete, Comparison::Sensitive, Comparison::Sensitive, $first ?? $path[0]);
    }

    /**
     * Answers the namespace of the PostgreSQL statement values.
     */
    public function statementNamespace(): string
    {
        return 'SqlSemantics\\Platform\\PostgreSql\\Statement\\';
    }
}
