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
use SqlSemantics\Contract\Mode;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexNamespace;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Model\Lowering\Productions;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Rules\LeafKeys;

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
final class Platform
{
    private static ?self $shared = null;

    /**
     * Shares the parser cache within this database package.
     */
    public static function shared(): self
    {
        return self::$shared ??= new self();
    }

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
        if ($release === null) {
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
     *
     * @return list<Model\Statement\Statement>
     */
    public function lower(Node $tree, LanguageProfile $profile, Leaves $leaves): array
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
     * @param list<string>|null $searchPath
     * @param list<Model\Statement\Declaration\Table> $tables
     */
    public function context(LanguageProfile $profile, ?array $searchPath, array $tables, bool $complete, ?IndexNamespace $indexes = null, ?Name $database = null): AnalysisContext
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
            $eligible = $indexes !== null && $complete
                ? ($schema === 'pg_temp' || $indexes->known($name))
                : ($schema !== 'pg_temp' && $schema !== 'pg_catalog');
            if ($first === null && $eligible) {
                $first = $name;
            }
        }

        return new AnalysisContext($profile, $path, $tables, $complete, Comparison::Sensitive, Comparison::Sensitive, $first ?? $path[0], $indexes ?? new IndexNamespace(), $database);
    }

    /**
     * Answers the namespace of the PostgreSQL statement values.
     */
    public function statementNamespace(): string
    {
        return 'SqlSemantics\\Platform\\PostgreSql\\Statement\\';
    }
}
