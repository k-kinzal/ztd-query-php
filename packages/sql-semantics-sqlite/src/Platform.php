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
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Lowering\Leaves;
use SqlSemantics\Platform\Sqlite\Model\Lowering\Productions;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Index;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Platform\Sqlite\Rules\Definition\DropRestrictions;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Index\ConstraintIndexes;
use SqlSemantics\Platform\Sqlite\Rules\LeafKeys;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Vacuum;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateIndex;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateVirtualTable;

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
     * Identifies the parsed operand edges SQLite does not resolve.
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
            if ($object instanceof Vacuum && $object->ignoresDestination() && $object->into !== null) {
                $discarded[spl_object_id($object)] = 'into';
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
     * @param list<Table> $tables
     * @param list<Index> $indexes
     * @param list<Name>|null $opaqueIndexSchemas
     * @throws InvalidArgumentException When the path does not start with main
     */
    public function context(
        LanguageProfile $profile,
        ?array $searchPath,
        array $tables,
        bool $complete,
        array $indexes = [],
        ?array $opaqueIndexSchemas = null,
    ): AnalysisContext {
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

        return new AnalysisContext(
            $profile,
            $path,
            $tables,
            $complete,
            Comparison::AsciiInsensitive,
            Comparison::AsciiInsensitive,
            $path[1],
            $indexes,
            $opaqueIndexSchemas,
        );
    }

    /**
     * Exports the declarations in a binding with the facts required by later schema changes.
     *
     * @return list<Table>
     */
    public function declarations(BoundStatement $binding): array
    {
        $definition = $binding->statement;
        if (!$definition instanceof CreateTable) {
            return $binding->facts->declarations;
        }

        $removal = new DropRestrictions();

        return array_map(
            static fn (Table $table): Table => $removal->attach($definition, $binding->facts, $table),
            $binding->facts->declarations,
        );
    }

    /**
     * Exports an explicit index identity once its ordinary owner has resolved.
     */
    public function indexDeclaration(BoundStatement $binding): ?Index
    {
        $definition = $binding->statement;
        if (!$definition instanceof CreateIndex) {
            return null;
        }

        $owner = $binding->facts->relation($definition)->table;
        Check::input($owner instanceof DeclaredTable && $owner->table->kind === RelationKind::BaseTable, 'An index declaration requires a resolved ordinary table.');
        $comparison = Comparison::AsciiInsensitive;
        Check::input(!str_starts_with($comparison->fold($definition->name->name->value), 'sqlite_'), 'An ordinary index cannot belong to the reserved SQLite namespace.');

        return new Index($owner->table->name->schema ?? $binding->context->declarationSchema, $definition->name->name, $owner->table->name->name);
    }

    /**
     * Exports the protected native index names of an ordinary table declaration.
     *
     * @return list<Index>|null Null when module or opaque key rules leave the implicit names unknown
     */
    public function constraintIndexes(BoundStatement $binding): ?array
    {
        $definition = $binding->statement;
        if ($definition instanceof CreateVirtualTable) {
            return null;
        }
        if (!$definition instanceof CreateTable) {
            return [];
        }
        $layout = (new ConstraintIndexes())->layout($definition);
        if ($layout === null) {
            return null;
        }

        $owner = $binding->facts->declarations[0]->name;
        $schema = $owner->schema ?? $binding->context->declarationSchema;
        $indexes = [];
        for ($ordinal = 1; $ordinal <= $layout->count; $ordinal++) {
            $name = new Name('sqlite_autoindex_' . $owner->name->value . '_' . $ordinal);
            $indexes[] = new Index($schema, $name, $owner->name, false, $layout->primaryStorage === $ordinal);
        }

        return $indexes;
    }

    /**
     * Answers the namespace of the SQLite statement values.
     */
    public function statementNamespace(): string
    {
        return 'SqlSemantics\\Platform\\Sqlite\\Statement\\';
    }
}
