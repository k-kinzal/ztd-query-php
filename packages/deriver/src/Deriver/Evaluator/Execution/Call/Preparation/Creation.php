<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call\Preparation;

use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Creation\Access;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Signatures;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Hierarchy\Builtins;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;

/**
 * Checks constructor access and argument modes before evaluating constructor arguments.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Creation
{
    /**
     * @param Evaluator $machine Declaration and evaluator world
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Resolves a source, handled, native, or absent constructor.
     * @param CallableGraph $caller Lexical caller
     * @param Instruction $instruction Allocation prototype
     * @param State $state \Deriver\Deriver\Evaluator\Execution\State before argument effects
     * @return Target Constructor signature or early allocation error
     */
    public function resolve(CallableGraph $caller, Instruction $instruction, State $state): Target
    {
        $context = $this->machine->context;
        $source = $state->value($instruction->operands[0]);
        if ($source->kind === \Deriver\Deriver\Evaluator\TermKind::Array || $source->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && !is_string($source->literal)) {
            return new Target(error: 'Error');
        }
        $access = new Access($this->machine);
        $class = $access->name($source, $caller, $state, ($instruction->attributes['literal-class'] ?? true) === true);
        if ($class !== null && in_array(strtolower($class), ['', 'self', 'parent', 'static'], true)) {
            return new Target(error: 'Error');
        }
        if ($class === null) {
            return new Target();
        }
        if ($access->lifecycle($class, $caller, $state, false) !== null) {
            return new Target(error: 'Error');
        }
        $symbol = (new Dispatch($context->program))->method($class, '__construct');
        if ($symbol !== null || isset($context->evaluators->evaluators[strtolower($class . '::__construct')])) {
            return new Target((new CallResolution($context))->body($symbol ?? $class . '::__construct', $instruction));
        }
        $signatures = new Signatures($context->program);
        $family = $signatures->family($class);
        if ($family !== '') {
            return new Target($signatures->graph($family, '__construct', $instruction->source));
        }
        return isset($context->program->classes()[strtolower($class)]) || (new Builtins())->name($class) !== null ? new Target(new CallableGraph($class . '::__construct', [], [], $instruction->source)) : new Target();
    }
}
