<?php

declare(strict_types=1);

namespace Deriver\Source\Declaration;

use Deriver\Reference\SourceComment;
use Deriver\Source\Compilation\EffectInspection;

/**
 * Lists attached PHPDoc from captured syntax without compiling callable bodies.
 * @visibility root
 */
final class Comments
{
    /**
     * @param ProjectIndex $index Captured sources and source positions
     * @param string $symbol Declaration whose syntax should be inspected
     * @return list<SourceComment> Source-ordered raw comments, deduplicated by node range
     */
    public function within(ProjectIndex $index, string $symbol): array
    {
        $source = $index->declarations[(new \Deriver\ControlFlow\CallableIdentity())->key($symbol)] ?? null;
        if ($source === null) {
            return [];
        }
        $pending = [$source->node];
        $result = [];
        $builder = $index->builder($source->path);
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node !== $source->node && ($node instanceof \PhpParser\Node\FunctionLike || $node instanceof \PhpParser\Node\Stmt\ClassLike)) {
                continue;
            }
            $comment = $node->getDocComment();
            if ($comment !== null) {
                $reference = $builder->source($node);
                $result[$reference->id()] = new SourceComment($reference, $comment->getText());
            }
            array_push($pending, ...(new EffectInspection())->children($node));
        }
        $result = array_values($result);
        usort($result, static fn (SourceComment $a, SourceComment $b): int => $a->source->start <=> $b->source->start);
        return $result;
    }
}
