<?php

declare(strict_types=1);

namespace SqlSemantics\Validation;

use SqlParser\Lexer\SourceException;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvariantViolation;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Script;
use SqlSemantics\Statement\Statement;

/**
 * Establishes everything an operation publishes: audited structure, complete facts, and corresponding SQL.
 *
 * Rule: CORE-PUBLICATION-001. (1) The structure is audited against the closed
 * immutable value domain. (2) The statement derives its facts against the
 * context; every scalar, relation and query node must have received a fact.
 * (3) The structure is rendered to typed pieces and joined to text. (4) The
 * text is parsed under the same profile and lowered by the same rules, and the
 * result must correspond operand by operand to the structure. Spelled regions
 * must only re-spell the rendered tokens (CORE-SPELLING-001). Any failure
 * discards the candidate. Trusted: the parser, the lowering and derivation
 * rules, and these checks. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Publication
{
    /**
     * Derives the facts and the checked SQL of a statement.
     *
     * @return array{Facts, string}
     * @throws InvariantViolation When the rendered SQL does not correspond to the structure
     */
    public function establish(AnalysisContext $context, Statement $statement, \SqlSemantics\Statement\Source\SourceMap $sources = new \SqlSemantics\Statement\Source\SourceMap()): array
    {
        $platform = Platforms::of($context->profile->grammar->database());
        $graph = new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', $platform->statementNamespace()]);
        $objects = $graph->objects($statement);
        $graph->objects($sources);
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
            if ($object instanceof Scalar || $object instanceof Relation || $object instanceof Query) {
                Check::invariant($facts->covers($object), 'Derivation left a ' . $object::class . ' without facts.');
            }
            if ($object instanceof Scalar && ($replacement = $facts->scalar($object)->replacement) !== null) {
                Check::invariant($replacement !== $object && in_array($replacement, $graph->objects($object), true) && $facts->covers($replacement), 'A scalar replacement is a bound strict descendant of its occurrence.');
            }
        }
        $graph->objects($facts);

        $output = new Output($platform->codec($context->profile));
        $statement->render($output);
        $sql = (new Lexical())->join($output->pieces()) . $output->trailing();
        (new Spellings($platform->parser($context->profile), $platform->productions($context->profile), $platform->leafKeys($context->profile)))->check((new Lexical())->join($output->canonical()), $sql);
        try {
            $tree = $platform->parser($context->profile)->parse($sql);
        } catch (SourceException $error) {
            throw new InvariantViolation('The rendered SQL is outside the grammar: ' . $error->getMessage() . ' in: ' . $sql, 0, $error);
        }
        $difference = (new Equivalence())->difference($statement, $this->root($platform->lower($tree, $context->profile, new Leaves())));
        Check::invariant($difference === null, 'The rendered SQL does not correspond to the statement at ' . $difference . ' in: ' . $sql);

        return [$facts, $sql];
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
