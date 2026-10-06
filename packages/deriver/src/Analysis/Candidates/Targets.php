<?php

declare(strict_types=1);

namespace Deriver\Analysis\Candidates;

use Deriver\Exception\InvalidInputException;
use Deriver\Reference\ExpressionRef;
use Deriver\Source\Declaration\ProjectIndex;

/**
 * Resolves source ranges to definitions without exposing internal register names.
 * @visibility root
 */
final class Targets
{
    /**
     * Resolves an exact byte range to one lowered expression.
     * @throws InvalidInputException If no expression matches the requested range
     */
    public function expression(ProjectIndex $index, string $path, int $start, int $end, string $role): ExpressionRef
    {
        if (!isset($index->files[$path]) || $start < 0 || $end < $start || $end > strlen($index->files[$path]->contents)) {
            throw new InvalidInputException('The expression range is outside captured source.');
        }
        $owners = [];
        foreach ($index->declarations as $declaration) {
            if ($declaration->path === $path && $declaration->node->getStartFilePos() <= $start && $declaration->node->getEndFilePos() + 1 >= $end) {
                $owners[] = $declaration;
            }
        }
        usort($owners, static fn ($a, $b): int => ($a->node->getEndFilePos() - $a->node->getStartFilePos()) <=> ($b->node->getEndFilePos() - $b->node->getStartFilePos()));
        foreach ($owners as $owner) {
            $selected = null;
            foreach ($index->callable($owner->symbol)->blocks ?? [] as $block) {
                foreach ($block->instructions as $instruction) {
                    if ($instruction->source->start !== $start || $instruction->source->end !== $end || in_array($instruction->operation, ['local', 'field-address', 'element-address', 'static-address'], true)) {
                        continue;
                    }
                    if ($role !== 'value' && $role !== $instruction->operation) {
                        continue;
                    }
                    $selected = new ExpressionRef($instruction->source, $owner->symbol, $instruction->result);
                }
            }
            if ($selected !== null) {
                return $selected;
            }
        }
        throw new InvalidInputException('The range and syntactic role do not identify an expression.');
    }
}
