<?php

declare(strict_types=1);

namespace SqlSemantics\Facade;

use InvalidArgumentException;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\Node;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\Dialect;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\Mode;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\Platform;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Contract\Session;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Validation\LeafEmbedding;
use SqlSemantics\Validation\Publication;
use SqlSemantics\Validation\TokenCorrespondence;
use SqlSemantics\Validation\ValueGraph;

/**
 * Analyzes SQL of one fixed language profile into read-only operations.
 *
 * An operation holds the concrete structure of the statement, the facts
 * derived against an explicit declaration context, and SQL rendered from that
 * structure. Nothing here changes an operation: a different statement is
 * analyzed anew or built from explicit inputs.
 *
 * @visibility public
 * @example Resolving a query against the declaration it depends on
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
 *     $query = $semantics->analyze('SELECT name FROM users WHERE id = 1', [$users]);
 *     $query->field('name')->column() === $users->declarations()[0]->columns[1] // => true
 * @example Rendering SQL from the analyzed structure
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $semantics->analyze('select   1')->toString() // => 'SELECT 1'
 * @example Analyzing a tree the caller parsed with the parser of the profile
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $tree = $semantics->parser()->parse('select 2');
 *     $semantics->analyze($tree)->toString() // => 'SELECT 2'
 * @example Finding the statements of a script
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $semantics->split("SELECT 1; SELECT ';'") // => ['SELECT 1;', " SELECT ';'"]
 */
final class Semantics
{
    private readonly Platform $platform;

    private readonly LanguageProfile $profile;

    private readonly SqlParser $parser;

    /**
     * @var list<string>|null
     */
    private readonly ?array $searchPath;

    /**
     * Fixes the language profile: database, grammar release, session mode and parameter style.
     *
     * @param Dialect $dialect The database
     * @param string|null $grammarVersion A release tag the database package ships, or null for its default
     * @param Mode|null $mode The session settings SQL is read under, or null for the defaults of the database
     * @param ParameterStyle $parameters Which parameter markers are read; the named style adds `:name`
     * @param SearchPath|null $searchPath The schemas an unqualified relation name is searched in, or null for the default of the database
     *
     * @throws InvalidArgumentException When the release, mode or search path does not belong to the database
     */
    public function __construct(Dialect $dialect, ?string $grammarVersion = null, ?Mode $mode = null, ParameterStyle $parameters = ParameterStyle::Native, ?SearchPath $searchPath = null)
    {
        $this->platform = Platforms::of($dialect->database());
        $this->profile = $this->platform->profile($grammarVersion, $mode, $parameters);
        $this->parser = $this->platform->parser($this->profile);
        $this->searchPath = $searchPath?->schemas;
        $this->platform->context($this->profile, $this->searchPath, [], false);
    }

    /**
     * Answers the fixed language profile.
     */
    public function profile(): LanguageProfile
    {
        return $this->profile;
    }

    /**
     * Answers the parser of the language profile.
     *
     * A caller that parses SQL itself, to read the syntax tree before the
     * analysis or to report a syntax error as the database does, parses with
     * this parser and passes the tree to analyze().
     */
    public function parser(): SqlParser
    {
        return $this->parser;
    }

    /**
     * Creates an immutable declaration context.
     *
     * Null declarations give an open context: relations that are not declared
     * may exist. A list, even an empty one, gives a context that enumerates
     * every relation unless `$complete` is false. An operation in the list
     * contributes the declarations it provides; it is not executed, and ALTER,
     * DROP and writes contribute nothing.
     *
     * @param list<Table|Operation>|null $declarations The declarations, or null for an open context without any
     * @param bool $complete Whether a given list enumerates every relation
     * @param SearchPath|null $searchPath The schemas an unqualified relation name is searched in, or null for the path fixed at construction
     * @param Session|null $session The session statements are resolved in, or null for a new session with the server defaults
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a declaration belongs to another language profile
     * @throws InvalidArgumentException When the search path does not belong to the database
     */
    public function context(?array $declarations = null, bool $complete = true, ?SearchPath $searchPath = null, ?Session $session = null): AnalysisContext
    {
        $tables = [];
        foreach ($declarations ?? [] as $declaration) {
            if ($declaration instanceof Operation) {
                Check::input($this->profile->sharesDeclarationsWith($declaration->profile()), 'A declaring operation must belong to the grammar release of the selected language profile.');
                array_push($tables, ...$declaration->declarations());
            } else {
                $tables[] = $declaration;
            }
        }

        return $this->platform->context($this->profile, $searchPath?->schemas ?? $this->searchPath, $tables, $declarations !== null && $complete)->withSession($session);
    }

    /**
     * Analyzes one input into an operation against an explicit declaration context.
     *
     * The input is SQL text, or the syntax tree of one input that the parser
     * of this profile (parser()) produced; a tree of another parser is read
     * as if this profile had produced it.
     *
     * Semantic problems of grammatical SQL, such as a missing column, are
     * facts of the returned operation. The operation is returned only after
     * its structure was confirmed to hold every operand of the input and its
     * rendered SQL was confirmed to carry the same significant tokens.
     *
     * @param string|Node $sql The SQL text, or the tree parser() produced for it
     * @param list<Table|Operation>|AnalysisContext|null $context The declarations, a prepared context, or null for an open context
     *
     * @throws AnalysisException When the SQL is outside the selected grammar
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the context belongs to another language profile
     */
    public function analyze(string|Node $sql, array|AnalysisContext|null $context = null): Operation
    {
        $declarations = $context instanceof AnalysisContext ? $context : $this->context($context);
        Check::input($this->profile->compatibleWith($declarations->profile), 'The context must match the selected language profile.');
        if ($sql instanceof Node) {
            $tree = $sql;
        } else {
            try {
                $tree = $this->parser->parse($sql);
            } catch (SourceException $error) {
                throw new AnalysisException($error->getMessage(), 0, $error);
            }
        }
        $leaves = new Leaves();
        $statement = (new Publication())->root($this->platform->lower($tree, $this->profile, $leaves));
        $operation = new Operation($declarations, $statement);

        $graph = new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', $this->platform->statementNamespace()]);
        $dropped = (new LeafEmbedding())->dropped($leaves, $graph->objects($statement));
        Check::invariant($dropped === null, 'The statement lost an operand of the input: ' . ($dropped === null ? '' : $dropped::class));
        $tokens = new TokenCorrespondence();
        $keys = $this->platform->leafKeys($this->profile);
        $productions = $this->platform->productions($this->profile);
        $difference = $tokens->difference($tokens->keys($tree, $productions, $keys), $tokens->keys($this->parser->parse($operation->toString()), $productions, $keys));
        Check::invariant($difference === null, 'The rendered SQL does not carry the tokens of the input: ' . $difference . ' in: ' . $operation->toString());

        return $operation;
    }

    /**
     * Analyzes each statement of a script against the same explicit declaration context.
     *
     * No statement is executed for the ones after it.
     *
     * @param list<Table|Operation>|AnalysisContext|null $context The declarations, a prepared context, or null for an open context
     * @return list<Operation>
     *
     * @throws AnalysisException When a statement is outside the selected grammar
     */
    public function analyzeAll(string $sql, array|AnalysisContext|null $context = null): array
    {
        $declarations = $context instanceof AnalysisContext ? $context : $this->context($context);
        $operations = [];
        foreach ($this->split($sql) as $statement) {
            $operations[] = $this->analyze($statement, $declarations);
        }

        return $operations;
    }

    /**
     * Finds the statement boundaries of a script, as the database finds them.
     *
     * Each text ends with its own terminator; trailing whitespace and comments
     * stay with the last statement. A semicolon inside a string, a comment, or
     * a compound statement ends nothing.
     *
     * @return list<string>
     *
     * @throws AnalysisException When a statement is outside the selected grammar
     */
    public function split(string $sql): array
    {
        try {
            return (new Splitter($this->parser))->split($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
    }
}
