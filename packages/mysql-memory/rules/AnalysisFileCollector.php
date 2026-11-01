<?php

declare(strict_types=1);

namespace Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\FileNode;

/**
 * Makes global coverage run even when no generator or runtime class is declared.
 * PHPStan invokes collected-data rules only when a collector has produced data.
 *
 * @implements Collector<FileNode, true>
 * @visibility Rules
 */
final class AnalysisFileCollector implements Collector
{
    /**
     * @return class-string<FileNode>
     */
    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @param FileNode $node
     * @return true
     */
    public function processNode(Node $node, Scope $scope): bool
    {
        return true;
    }
}
