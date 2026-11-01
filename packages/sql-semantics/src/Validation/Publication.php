<?php

declare(strict_types=1);

namespace SqlSemantics\Validation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Construction\Facts;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Script;
use SqlSemantics\Statement\Statement;

/**
 * Resolves a constructed statement without passing it through the SQL front end.
 *
 * Construction checks the value domain and resolves its operands against the
 * declarations. SQL reconstruction is a separate consumer, checked by the
 * round-trip fuzz target. Re-parsing here would make generated-value execution
 * depend on the parser and lowerer that the property is intended to bypass.
 *
 * @visibility SqlSemantics
 */
final class Publication
{
    /**
     * Returns the concrete statement with meanings attached to its SQL occurrences.
     *
     */
    public function publish(AnalysisContext $context, Statement $statement, \SqlSemantics\Statement\Source\SourceMap $sources = new \SqlSemantics\Statement\Source\SourceMap()): \SqlSemantics\Statement\AnalyzedStatement
    {
        if ($statement instanceof \SqlSemantics\Statement\AnalyzedStatement) {
            $sources = $sources->retainingInputNotices($statement);
        }
        $occurrences = new \SqlSemantics\Construction\Occurrences($sources);
        $result = $occurrences->copy($statement);
        Check::input($result instanceof \SqlSemantics\Statement\SqlStatement, 'An analyzed statement has a concrete publication implementation.');
        [$facts, $statements] = $this->derive($context, $result, $occurrences->sources());
        foreach ($statements as [$node, $environment, $information]) {
            Check::invariant($node instanceof \SqlSemantics\Statement\SqlStatement, 'Nested SQL statements retain concrete publication implementations.');
            $information = $node === $result ? $facts : $information;
            $notices = $information->notices;
            $node->initializeStatementInformation(new \SqlSemantics\Statement\StatementSemantics($environment->profile, $information->output, $information->declarations, $information->diagnostics, $notices));
        }
        return $result;
    }

    /**
     * Derives facts from the supplied values and renders their SQL independently.
     *
     * @return array{Facts, string, list<array{Statement, AnalysisContext, Facts}>}
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a retained value is outside the closed immutable domain
     */
    public function establish(AnalysisContext $context, Statement $statement, \SqlSemantics\Statement\Source\SourceMap $sources = new \SqlSemantics\Statement\Source\SourceMap()): array
    {
        $platform = Platforms::of($context->profile->grammar->database());
        $output = new Output($platform->codec($context->profile));
        $statement->render($output);
        [$facts, $statements] = $this->derive($context, $statement, $sources);
        $sql = (new Lexical())->join($output->pieces()) . $output->trailing();
        return [$facts, $sql, $statements];
    }

    /**
     * Resolves the supplied semantic values without invoking SQL rendering.
     *
     * @return array{Facts, list<array{Statement, AnalysisContext, Facts}>}
     */
    public function derive(AnalysisContext $context, Statement $statement, \SqlSemantics\Statement\Source\SourceMap $sources = new \SqlSemantics\Statement\Source\SourceMap()): array
    {
        $platform = Platforms::of($context->profile->grammar->database());
        $graph = new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', $platform->statementNamespace()]);
        $graph->objects($context, true);
        $objects = $graph->objects($statement, complete: false);
        $graph->objects($sources, complete: false);
        foreach ($sources->origins as $origin) {
            Check::input(in_array($origin->node, $objects, true), 'A source location belongs to an occurrence of this statement.');
        }

        foreach ($sources->notices as $notice) {
            Check::input(in_array($notice->subject, $objects, true), 'An input notice belongs to an occurrence of this statement.');
        }
        $derivation = new Derivation($context, $sources);
        $derivation->statement($statement);
        $facts = $derivation->facts();
        foreach ($objects as $object) {
            if ($object instanceof Operand || $object instanceof Relation || $object instanceof Query) {
                Check::invariant($facts->covers($object), 'Derivation left a ' . $object::class . ' without facts.');
            }
            if ($object instanceof Operand && ($replacement = $facts->scalar($object)->replacement) !== null) {
                Check::invariant($replacement !== $object && in_array($replacement, $graph->objects($object, complete: false), true) && $facts->covers($replacement), 'A scalar replacement is a bound strict descendant of its occurrence.');
            }
        }

        return [$facts, $derivation->statements()];
    }

    /**
     * Answers the root of a lowered input: its only statement, or a script of none or several.
     *
     * @param list<Statement> $statements
     */
    public function root(array $statements): Statement
    {
        return count($statements) === 1 ? $statements[0] : new Script($statements);
    }
}
