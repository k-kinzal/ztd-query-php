<?php

declare(strict_types=1);

namespace SqlSemantics\Testing\PostgreSql;

use InvalidArgumentException;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\SqlParser;
use SqlSemantics\AnalysisContext as Context;
use SqlSemantics\Contract\Mode;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Contract\SearchPath;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\SemanticException;
use SqlSemantics\Platform\PostgreSql\Model\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\WarningDiagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Validation\LeafEmbedding;
use SqlSemantics\Platform\PostgreSql\Model\Validation\ValueGraph;
use SqlSemantics\Platform\PostgreSql\Platform;

/**
 * Parses and measures grammar fixtures for tests, fuzz targets and benchmarks.
 */
final class Inspection
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
     * @param list<Table|InspectedStatement>|Context|null $declarations A declaration list, a prepared context, or null for an open context
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
            if ($declaration instanceof InspectedStatement) {
                Check::input($this->profile->compatibleWith($declaration->profile()), 'A declaring operation must belong to the selected language profile.');
                array_push($tables, ...$declaration->declarations());
            } else {
                $tables[] = $declaration;
            }
        }

        return $this->platform->context($this->profile, $this->searchPath, $tables, $declarations !== null && $complete);
    }

    /**
     * Returns a checked operation with diagnostic facts and declarations.
     *
     * @param list<Table|InspectedStatement>|Context|null $context A declaration list, a prepared context, or null for an open context
     *
     * @throws AnalysisException When the grammar rejects the SQL
     * @throws SemanticException When the analysis establishes a semantic problem
     * @throws InvalidConstruction When the context belongs to another dialect or language profile
     */
    public function inspect(string $sql, array|Context|null $context = null): InspectedStatement
    {
        $operation = $this->diagnose($sql, $context);
        $errors = array_values(array_filter(
            $operation->facts->diagnostics,
            static fn (Diagnostic $problem): bool => !($problem instanceof WarningDiagnostic && $problem->warning()),
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
     * @param list<Table|InspectedStatement>|Context|null $context
     * @throws AnalysisException When the grammar rejects the SQL
     * @throws InvalidConstruction When the context belongs to another dialect or language profile
     */
    public function diagnose(string $sql, array|Context|null $context = null): InspectedStatement
    {
        $declarations = $this->context($context);
        try {
            $tree = $this->parser->parse($sql);
        } catch (SourceException $error) {
            throw new AnalysisException($error->getMessage(), 0, $error);
        }
        $leaves = new Leaves();
        $statement = (new RenderAudit())->root($this->platform->lower($tree, $this->profile, $leaves));
        $operation = new InspectedStatement($declarations, $statement);

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
}
