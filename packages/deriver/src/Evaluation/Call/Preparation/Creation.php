<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Preparation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Creation\Access;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;

/**
 * Checks constructor access and argument modes before evaluating constructor arguments.
 * @visibility root
 */
final class Creation
{
    /**
     * @param Machine $machine Declaration and model world
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Resolves a source, modeled, native, or absent constructor.
     * @param CallableGraph $caller Lexical caller
     * @param Instruction $instruction Allocation prototype
     * @param State $state State before argument effects
     * @return Target Constructor signature or early allocation error
     */
    public function resolve(CallableGraph $caller, Instruction $instruction, State $state): Target
    {
        $context = $this->machine->context;
        $source = $state->value($instruction->operands[0]);
        if ($source->kind === 'array' || $source->kind === 'constant' && !is_string($source->literal)) {
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
        if ($symbol !== null || isset($context->models->models[strtolower($class . '::__construct')])) {
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
