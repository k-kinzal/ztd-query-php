<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Analysis\Session;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Cache;
use Deriver\Evaluation\Candidate\Context;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Index;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\Budget;
use JsonException;
use LogicException;

/**
 * Constructs isolated dependency queries from captured source fixtures.
 * @visibility root
 */
final class CandidateFixture
{
    /**
     * Creates a query context without advancing a program state.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public static function evaluator(string $source = 'function target($x){return $x+2;}', Configuration $configuration = new Configuration(), Budget $budget = new Budget()): Derivation
    {
        $session = new Session(new ProjectInput([new SourceFile('candidate.php', '<?php ' . $source)]), $configuration);
        return new Derivation(new Context(new Index($session->program), $configuration, $session->models, $budget, new Cache()));
    }

    /**
     * Selects an existing fixture declaration.
     * @throws LogicException If the fixture does not declare the callable
     */
    public static function frame(Derivation $evaluator, string $symbol = 'target'): Frame
    {
        $graph = $evaluator->context->index->graph($symbol) ?? throw new LogicException('Missing fixture callable.');
        return new Frame($graph, 'source:' . $symbol);
    }

    /**
     * Selects a fixture instruction without asking for its value.
     * @throws LogicException If the fixture does not contain the operation
     */
    public static function instruction(Frame $frame, string $operation): Instruction
    {
        foreach ($frame->graph->definitions as $instruction) {
            if ($instruction->operation === $operation) {
                return $instruction;
            }
        }
        throw new LogicException('Missing fixture operation: ' . $operation);
    }
    /**
     * Collects one distinct return value while preserving guarded alternatives in the evaluator.
     * @throws LogicException If the fixture produces multiple distinct values
     */
    public static function value(Derivation $evaluator): \Deriver\Value\Term
    {
        $alternatives = (new \Deriver\Evaluation\Candidate\Choices())->alternatives($evaluator->returns(self::frame($evaluator), 64));
        $values = [];
        foreach ($alternatives as [$value, $_]) {
            $values[(new \Deriver\Value\Identity())->key($value)] = $value;
        }
        if (count($values) !== 1) {
            throw new LogicException('Fixture must produce one distinct value.');
        }
        return reset($values);
    }
}
