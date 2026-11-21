<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use InvalidArgumentException;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\SqlParser;
use SqlSemantics\AnalysisContext as Context;
use SqlSemantics\Analyzer;
use SqlSemantics\Contract\Mode;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Contract\SearchPath;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Splitter;
use SqlSemantics\Platform\PostgreSql\Model\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Operation;
use SqlSemantics\Platform\PostgreSql\Model\Validation\LeafEmbedding;
use SqlSemantics\Platform\PostgreSql\Model\Validation\Publication;
use SqlSemantics\Platform\PostgreSql\Model\Validation\TokenCorrespondence;
use SqlSemantics\Platform\PostgreSql\Model\Validation\ValueGraph;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblem;
use SqlSemantics\Query;

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
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
 *     $query = $semantics->analyze('SELECT name FROM users WHERE id = 1', [$users]);
 *     $query->field('name')->column() === $users->declarations()[0]->columns[1] // => true
 * @example Rendering SQL from the analyzed structure
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $semantics->analyze('select   1')->toString() // => 'SELECT 1'
 * @example Finding the statements of a script
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $semantics->split("SELECT 1; SELECT ';'") // => ['SELECT 1;', " SELECT ';'"]
 */
final class Semantics implements Analyzer
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
     * @param string|null $grammarVersion A release tag the database package ships, or null for its default
     * @param Mode|null $mode The session settings SQL is read under, or null for the defaults of the database
     * @param ParameterStyle $parameters Which parameter markers are read; the named style adds `:name`
     * @param SearchPath|null $searchPath The schemas an unqualified relation name is searched in, or null for the default of the database
     *
     * @throws InvalidArgumentException When the release, mode or search path does not belong to the database
     */
    public function __construct(?string $grammarVersion = null, ?Mode $mode = null, ParameterStyle $parameters = ParameterStyle::Native, ?SearchPath $searchPath = null)
    {
        $this->platform = Platform::shared();
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
     * Prepares an immutable declaration context or verifies a supplied context.
     *
     * Null declarations give an open context: relations that are not declared
     * may exist. A list, even an empty one, gives a context that enumerates
     * every relation unless `$complete` is false. An operation in the list
     * contributes the declarations it provides; it is not executed, and ALTER,
     * DROP and writes contribute nothing.
     *
     * @param list<Table|Operation>|Context|null $declarations A declaration list, a prepared context, or null for an open context
     * @param bool $complete Whether a given list enumerates every relation
     *
     * @throws InvalidConstruction When a declaration belongs to another language profile
     */
    public function context(array|Context|null $declarations = null, bool $complete = true): AnalysisContext
    {
        if ($declarations instanceof Context) {
            Check::input($declarations instanceof AnalysisContext, 'The context must belong to the selected database.');
            Check::input($this->profile->compatibleWith($declarations->profile), 'The context must match the selected language profile.');

            return $declarations;
        }
        $tables = [];
        foreach ($declarations ?? [] as $declaration) {
            if ($declaration instanceof Operation) {
                Check::input($this->profile->compatibleWith($declaration->profile()), 'A declaring operation must belong to the selected language profile.');
                array_push($tables, ...$declaration->declarations());
            } else {
                $tables[] = $declaration;
            }
        }

        return $this->platform->context($this->profile, $this->searchPath, $tables, $declarations !== null && $complete);
    }

    /**
     * Analyzes SQL and returns a checked operation against the given declarations.
     *
     * @param list<Table|Operation>|Context|null $context A declaration list, a prepared context, or null for an open context
     * @return Operation
     *
     * @throws AnalysisException When the grammar rejects the SQL
     * @throws SemanticException When the analysis establishes a semantic problem
     * @throws InvalidConstruction When the context belongs to another dialect or language profile
     */
    public function analyze(string $sql, array|Context|null $context = null): Query
    {
        $operation = $this->inspect($sql, $context);
        $errors = array_values(array_filter(
            $operation->facts->diagnostics,
            static fn (Diagnostic $problem): bool => !($problem instanceof AttributeProblem && $problem->warning()),
        ));
        if ($errors !== []) {
            throw new SemanticException($errors);
        }

        return $operation;
    }

    /**
     * Checks the structure and derives facts, including diagnostics for invalid statements.
     *
     * @visibility SqlSemantics
     * @param list<Table|Operation>|Context|null $context
     * @throws AnalysisException When the grammar rejects the SQL
     * @throws InvalidConstruction When the context belongs to another dialect or language profile
     */
    public function inspect(string $sql, array|Context|null $context = null): Operation
    {
        $declarations = $this->context($context);
        try {
            $tree = $this->parser->parse($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
        $leaves = new Leaves();
        $statement = (new Publication())->root($this->platform->lower($tree, $this->profile, $leaves));
        $operation = new Operation($declarations, $statement);

        $graph = new ValueGraph(['SqlSemantics\\Platform\\PostgreSql\\Model\\Statement\\', 'SqlSemantics\\Platform\\PostgreSql\\Model\\Contract\\', $this->platform->statementNamespace()]);
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
     * @param list<Table|Operation>|Context|null $context The declarations, a prepared context, or null for an open context
     * @return list<Operation>
     *
     * @throws AnalysisException When a statement is outside the selected grammar
     * @throws SemanticException When a statement has a semantic problem
     */
    public function analyzeAll(string $sql, array|Context|null $context = null): array
    {
        $declarations = $this->context($context);
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
