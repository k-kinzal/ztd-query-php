<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Language;

use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Context;
use Deriver\Query\ReturnQuery;
use Deriver\Value\Term;

/**
 * Reuses declaration coercions without invoking an execution machine.
 * @visibility root
 */
final class DeclarationCoercion
{
    /**
     * Checks only a demanded argument or return value against its declaration.
     */
    public function check(Derivation $derivation, Term $value, string $type, bool $strict, string $class = ''): Term
    {
        if ($type === 'mixed' || $type === '' || $value->kind === 'throwable') {
            return $value;
        }
        $context = $derivation->context;
        $types = new TypeBinding(new Context($context->index->program, new ReturnQuery('', budget: $context->budget), $context->configuration, $context->models, resources: $context->resources));
        $type = $types->scope($type, $class, $class);
        return (new \Deriver\Evaluation\Candidate\Choices())->apply('type:' . $type, [$value], static function (array $values) use ($types, $type, $strict): Term {
            $checked = $types->check($values[0], $type, $strict);
            return $checked->mustFail ? new Term('throwable', 'TypeError', [$values[0]]) : $checked->value;
        }, $context->budget->partitions);
    }
}
