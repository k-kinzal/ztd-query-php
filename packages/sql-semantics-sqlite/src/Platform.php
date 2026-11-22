<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use InvalidArgumentException;
use SqlParser\Parser\Node;
use SqlParser\Parser\SqlParser;
use SqlParser\Resource\VersionRegistry;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Contract\Mode;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\Sqlite\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\Sqlite\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\Sqlite\Model\Lowering\Leaves;
use SqlSemantics\Platform\Sqlite\Model\Lowering\Productions;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Platform\Sqlite\Rules\LeafKeys;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InList;

/**
 * Wires the SQLite grammar releases, lowering rules and spelling rules into the analysis.
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
     * Fixes the profile of a shipped SQLite release; SQLite reads SQL under no session mode.
     *
     * @throws InvalidArgumentException When the release is not shipped, a mode is given, or the installed grammar artifacts differ from the pinned ones
     */
    public function profile(?string $version, ?Mode $mode, ParameterStyle $parameters): LanguageProfile
    {
        if ($mode !== null) {
            throw new InvalidArgumentException('SQLite reads SQL under no session mode; ' . $mode::class . ' given.');
        }
        $release = GrammarRelease::tryFrom((new SqliteParser($version))->version());
        if ($release === null) {
            throw new InvalidArgumentException('No semantic profile exists for the selected grammar release.');
        }
        $artifact = (new VersionRegistry())->resolve('sqlite', $release->value);
        if (hash_file('sha256', $artifact->tablePath) !== $release->grammarDigest() || hash_file('sha256', $artifact->keywordPath) !== $release->keywordDigest()) {
            throw new InvalidArgumentException('The installed grammar artifacts do not match the fixed semantic profile.');
        }

        return new LanguageProfile($release, new LexicalSettings(), $parameters);
    }

    /**
     * Answers the parser of a profile, created once per profile.
     */
    public function parser(LanguageProfile $profile): SqlParser
    {
        $key = $profile->grammar->value;

        return $this->parsers[$key] ??= new SqliteParser($profile->grammar->value);
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
        return (new Lowering($this->productions($profile), $leaves))->statements($tree);
    }

    /**
     * Answers the name codec.
     */
    public function codec(LanguageProfile $profile): Codec
    {
        return new Codec();
    }

    /**
     * Opens the transient syntax writer used to recover lexical parameter occurrences.
     */
    public function syntaxOutput(): Output
    {
        return new Output(new Codec());
    }

    /**
     * Identifies the parsed operand edges discarded by SQLite's empty membership rule.
     *
     * @param list<object> $objects The already audited syntax graph
     * @return array<int, string>
     */
    public function discardedBindings(array $objects): array
    {
        $discarded = [];
        foreach ($objects as $object) {
            if ($object instanceof InList && $object->items === []) {
                $discarded[spl_object_id($object)] = 'operand';
            }
        }

        return $discarded;
    }

    /**
     * Answers the token comparison keys.
     */
    public function leafKeys(LanguageProfile $profile): LeafKeys
    {
        return new LeafKeys();
    }

    /**
     * Creates a context: SQLite searches temp, then main, then the attached schemas, and compares names without regard to ASCII case.
     *
     * @param list<string>|null $searchPath
     * @param list<Model\Statement\Declaration\Table> $tables
     * @throws InvalidArgumentException When the path does not start with main
     */
    public function context(LanguageProfile $profile, ?array $searchPath, array $tables, bool $complete): AnalysisContext
    {
        $schemas = $searchPath ?? ['main'];
        if ($schemas !== [] && strcasecmp($schemas[0], 'temp') === 0) {
            array_shift($schemas);
        }
        if ($schemas === [] || strcasecmp($schemas[0], 'main') !== 0) {
            throw new InvalidArgumentException('SQLite searches temp, main, then the attached schemas.');
        }
        $path = [new Name('temp')];
        foreach ($schemas as $schema) {
            $path[] = new Name($schema);
        }

        return new AnalysisContext($profile, $path, $tables, $complete, Comparison::AsciiInsensitive, Comparison::AsciiInsensitive, $path[1]);
    }

    /**
     * Answers the namespace of the SQLite statement values.
     */
    public function statementNamespace(): string
    {
        return 'SqlSemantics\\Platform\\Sqlite\\Statement\\';
    }
}
