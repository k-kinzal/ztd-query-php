<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Language;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Graph;
use Deriver\Value\Term;

/**
 * Language-level class identities, constants and object copies.
 * @visibility root
 */
final class Expressions
{
    /**
     * Uses common value expansion for language expressions.
     */
    public function __construct(private readonly Derivation $engine)
    {
    }

    /**
     * Expands a class constant in its lexical and called-class context.
     */
    public function constant(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $class = $this->engine->value($frame, $instruction->operands[0], $depth);
        $name = $this->engine->value($frame, $instruction->operands[1], $depth);
        return (new Choices())->apply('class-constant', [$class, $name], function (array $values) use ($frame, $instruction, $depth): Term {
            [$class, $name] = $values;
            if (!is_string($class->literal) || !is_string($name->literal)) {
                return new Term('class-constant', operands: $values, attributes: ['reason' => 'UNRESOLVED_CLASS']);
            }
            $index = $this->engine->context->index;
            $resolved = $index->className($class->literal, $frame->graph->body->className, $frame->calledClass);
            if (($instruction->attributes['class-name'] ?? false) === true) {
                return Term::constant($resolved);
            }
            $seen = [];
            $pending = [$resolved];
            while ($pending !== []) {
                $resolved = array_shift($pending);
                if ($resolved === '' || isset($seen[strtolower($resolved)])) {
                    continue;
                }
                $seen[strtolower($resolved)] = true;
                $declaration = $index->program->classes()[strtolower($resolved)] ?? null;
                $value = $declaration->constants[$name->literal] ?? null;
                if ($value?->kind === 'enum') {
                    return $value;
                }
                $body = $index->program->constant($resolved . '::' . $name->literal);
                if ($body !== null) {
                    return $this->engine->returns(new Frame(new Graph($body), $frame->identity . ':constant:' . $body->symbol, calledClass: $frame->calledClass), $depth);
                }
                array_push($pending, $declaration->parent ?? '', ...($declaration->interfaces ?? []));
            }
            return new Term('class-constant', operands: $values, attributes: ['reason' => 'MISSING_CONSTANT']);
        }, $this->engine->context->budget->partitions);
    }

    /**
     * Creates a distinct object identity retaining the copied allocation.
     */
    public function copy(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $value = $this->engine->value($frame, $instruction->operands[0], $depth);
        return (new Choices())->apply('clone', [$value], static fn (array $values): Term => $values[0]->kind === 'object' ? new Term('object', $frame->identity . ':' . $instruction->id, [$values[0]], $values[0]->attributes + ['copy_of' => $values[0]->literal, 'copy_context' => $frame->identity, 'copy_at' => $instruction->result]) : new Term('clone', operands: $values), $this->engine->context->budget->partitions);
    }

    /**
     * Requests a known object's string conversion through ordinary dispatch.
     */
    public function string(Frame $frame, Instruction $instruction, Term $value, int $depth): Term
    {
        return (new Choices())->apply('string-conversion', [$value], function (array $values) use ($frame, $instruction, $depth): Term {
            $object = $values[0];
            if ($object->kind !== 'object') {
                return $object;
            }
            return (new \Deriver\Evaluation\Candidate\Invocation\Callback($this->engine, $frame, $instruction, $depth))->value(Term::array([$object, Term::constant('__toString')]), []);
        }, $this->engine->context->budget->partitions);
    }

    /**
     * Supplies enum case identities from captured declarations.
     */
    public function enumCases(string $target): ?Term
    {
        if (!str_ends_with(strtolower($target), '::cases')) {
            return null;
        }
        $class = $this->engine->context->index->program->classes()[strtolower(substr($target, 0, -7))] ?? null;
        return $class?->enum === true ? Term::array(array_values(array_filter($class->constants, static fn (Term $value): bool => $value->kind === 'enum'))) : null;
    }
}
