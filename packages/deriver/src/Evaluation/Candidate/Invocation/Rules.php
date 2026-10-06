<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Invocation;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Evidence\Provenance;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Exception\ModelContractException;
use Deriver\Model\Expansion\Request;
use Deriver\Result\Evidence\Node;
use Deriver\Value\Term;
use Throwable;

/**
 * Selects user expansion rules before demanding any operands.
 * @visibility root
 */
final class Rules
{
    /**
     * Selects explicit rules from the captured configuration.
     */
    public function __construct(private readonly Derivation $engine)
    {
    }

    /**
     * Invokes the first selected rule and records only its demanded inputs.
     * @throws ModelContractException If a trusted rule requests an invalid input or fails
     */
    public function apply(Frame $frame, Instruction $instruction, int $depth, ?string $function = null): ?Term
    {
        $inputs = [];
        $expand = function (int|string $key) use ($frame, $instruction, $depth, $function, &$inputs): Term {
            $register = $function === null ? ($instruction->operands[$key] ?? null) : $this->argument($instruction, $key);
            if ($register === null) {
                throw new ModelContractException('The expansion rule requested an absent input: ' . $key);
            }
            return $inputs[$key] ??= $this->engine->value($frame, $register, $depth);
        };
        $request = new Request($function === null ? $instruction->operation : 'function', $function ?? $instruction->name, $instruction->source, $expand);
        $rules = $this->engine->context->configuration->expansionRules;
        usort($rules, static fn ($left, $right): int => $right->priority <=> $left->priority);
        foreach ($rules as $rule) {
            if (!$rule->matches($request)) {
                continue;
            }
            try {
                $result = ($rule->expand)($request);
            } catch (Throwable $error) {
                throw new ModelContractException($rule->id . ': ' . $error->getMessage(), previous: $error);
            }
            if ($result === null) {
                continue;
            }
            $this->engine->context->modelApplications++;
            return Provenance::attach($result, new Node('model-application', Provenance::inputs($inputs), $instruction->source, ['id' => $rule->id, 'version' => $rule->version, 'operation' => $request->operation, 'name' => $request->name]));
        }
        return null;
    }

    /**
     * Locates a direct actual argument by position or PHP parameter name.
     */
    public function argument(Instruction $instruction, int|string $key): ?string
    {
        foreach ($instruction->arguments as $position => $argument) {
            if ($position === $key || $argument->name === $key) {
                return $argument->register;
            }
        }
        return null;
    }
}
