<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Language;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;

/**
 * Language-level class identities, constants and object copies.
 * @visibility parent
 */
final class Expressions
{
    /**
     * Uses common value expansion for language expressions.
     */
    public function __construct(private readonly Evaluator $engine)
    {
    }

    /**
     * Resolves callable and class identities using the captured declaration world.
     */
    public function identity(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $values = array_map(fn (string $operand): Term => $this->engine->value($frame, $operand, $depth), $instruction->operands);
        return (new Choices($this->engine->context->provenance))->apply($instruction->operation, $values, function (array $values) use ($frame, $instruction): Term {
            foreach ($values as $value) {
                if ($value->kind === TermKind::Throwable) {
                    return $value;
                }
            }
            if ($instruction->operation === 'callable') {
                return new Term(TermKind::Callable, operands: $values, attributes: $instruction->attributes);
            }
            if ($instruction->operation === 'callable-method') {
                return new Term(TermKind::CallableMethod, operands: $values, attributes: $instruction->attributes);
            }
            [$object, $bound] = $values;
            if (in_array($object->kind, [TermKind::Constant, TermKind::Array], true)) {
                return Term::constant(false);
            }
            $class = $object->attributes['class'] ?? null;
            if ($object->kind === TermKind::Closure && is_string($bound->literal)) {
                $hierarchy = new Dispatch($this->engine->context->index->program);
                $name = $hierarchy->className($bound->literal, $frame->graph->body->className, $frame->calledClass);
                return Term::constant(strcasecmp(ltrim($name, '\\'), 'Closure') === 0);
            }
            if (!is_string($class) && $object->kind !== TermKind::Object) {
                $declared = $this->engine->context->index->receiverClass((string) ($object->attributes['type'] ?? ''));
                $scalar = $declared === '' && is_string($object->attributes['type'] ?? null) && $this->scalarOnly($object->attributes['type']);
                if ($scalar) {
                    return Term::constant(false);
                }
                $class = $declared !== '' && !str_contains((string) $object->attributes['type'], '|') ? $declared : $class;
            }
            if (is_string($class) && is_string($bound->literal)) {
                $hierarchy = new Dispatch($this->engine->context->index->program);
                $name = $hierarchy->className($bound->literal, $frame->graph->body->className, $frame->calledClass);
                if ($hierarchy->subtype($class, $name)) {
                    return Term::constant(true);
                }
                if ($hierarchy->complete($class)) {
                    return Term::constant(false);
                }
            }
            return new Term(TermKind::Operation, 'instanceof', $values, ['type' => 'bool', 'reason' => 'UNKNOWN_TYPE_RELATION']);
        }, $this->engine->context->budget->partitions);
    }

    /**
     * Reports whether a declared type admits only scalars, arrays and null, which no class can match.
     */
    public function scalarOnly(string $type): bool
    {
        $scalars = ['int', 'float', 'string', 'bool', 'true', 'false', 'null', 'array', 'integer', 'boolean', 'double'];
        foreach (explode('|', ltrim($type, '?')) as $member) {
            if (!in_array(strtolower(trim($member)), $scalars, true)) {
                return false;
            }
        }
        return $type !== '';
    }

    /**
     * Gives an object cast its own identity and retains the converted property values.
     */
    public function object(Frame $frame, Instruction $instruction, Term $value): Term
    {
        return (new Choices($this->engine->context->provenance))->apply('object-cast', [$value], static function (array $values) use ($frame, $instruction): Term {
            $value = $values[0];
            if (in_array($value->kind, [TermKind::Object, TermKind::Enum, TermKind::Closure, TermKind::Throwable], true)) {
                return $value;
            }
            if ($value->kind === TermKind::Array || $value->kind === TermKind::Constant) {
                $fields = $value->kind === TermKind::Array ? $value->operands : ($value->literal === null ? [] : ['scalar' => $value]);
                return new Term(TermKind::Object, $frame->identity . ':' . $instruction->id, $fields, ['type' => 'stdClass', 'class' => 'stdClass', 'cast' => true]);
            }
            return new Term(TermKind::Cast, 'object', [$value], ['type' => 'object']);
        }, $this->engine->context->budget->partitions);
    }

    /**
     * Expands a class constant in its lexical and called-class context.
     */
    public function constant(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $class = $this->engine->value($frame, $instruction->operands[0], $depth);
        $name = $this->engine->value($frame, $instruction->operands[1], $depth);
        return (new Choices($this->engine->context->provenance))->apply('class-constant', [$class, $name], function (array $values) use ($frame, $instruction, $depth): Term {
            [$class, $name] = $values;
            if (!is_string($class->literal) || !is_string($name->literal)) {
                return new Term(TermKind::ClassConstant, operands: $values, attributes: ['reason' => 'UNRESOLVED_CLASS']);
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
                $literal = $declaration->constants[$name->literal] ?? null;
                $value = $literal === null ? null : (new \Deriver\Deriver\Evaluator\Expression\LiteralEvaluator())->evaluate($literal);
                if ($value?->kind === TermKind::Enum) {
                    return $value;
                }
                $body = $index->program->constant($resolved . '::' . $name->literal);
                if ($body !== null) {
                    return $this->engine->returns(new Frame(new BodyIndex($body), $frame->identity . ':constant:' . $body->symbol, calledClass: $frame->calledClass), $depth);
                }
                array_push($pending, $declaration->parent ?? '', ...($declaration->interfaces ?? []));
            }
            return new Term(TermKind::ClassConstant, operands: $values, attributes: ['reason' => 'MISSING_CONSTANT']);
        }, $this->engine->context->budget->partitions);
    }

    /**
     * Creates a distinct object identity retaining the copied allocation.
     */
    public function copy(Frame $frame, Instruction $instruction, int $depth): Term
    {
        $value = $this->engine->value($frame, $instruction->operands[0], $depth);
        return (new Choices($this->engine->context->provenance))->apply('clone', [$value], static fn (array $values): Term => $values[0]->kind === TermKind::Object ? new Term(TermKind::Object, $frame->identity . ':' . $instruction->id, [$values[0]], $values[0]->attributes + ['copy_of' => $values[0]->literal, 'copy_context' => $frame->identity, 'copy_at' => $instruction->result]) : new Term(TermKind::Clone, operands: $values), $this->engine->context->budget->partitions);
    }

    /**
     * Requests a known object's string conversion through ordinary dispatch.
     */
    public function string(Frame $frame, Instruction $instruction, Term $value, int $depth): Term
    {
        return (new Choices($this->engine->context->provenance))->apply('string-conversion', [$value], function (array $values) use ($frame, $instruction, $depth): Term {
            $object = $values[0];
            if ($object->kind !== TermKind::Object) {
                return $object;
            }
            return (new \Deriver\Deriver\Evaluator\Invocation\Callback($this->engine, $frame, $instruction, $depth))->value(Term::array([$object, Term::constant('__toString')]), []);
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
        return $class?->enum === true ? Term::array(array_values(array_filter(array_map((new \Deriver\Deriver\Evaluator\Expression\LiteralEvaluator())->evaluate(...), $class->constants), static fn (Term $value): bool => $value->kind === TermKind::Enum))) : null;
    }
}
