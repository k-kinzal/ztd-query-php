<?php

declare(strict_types=1);

namespace Rules;

use Eris\Generator;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * Collects generator declarations independently of configured unused-class selection.
 *
 * @implements Collector<InClassNode, array{name: string, line: int}>
 * @visibility Rules
 */
final class GeneratorDeclarationCollector implements Collector
{
    /**
     * @return class-string<InClassNode>
     */
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     * @return array{name: string, line: int}|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        $class = $node->getClassReflection();
        $name = $class->getName();
        if (!str_starts_with($name, 'Tests\\Generator\\') || (!str_ends_with($name, 'Generator') && !$class->implementsInterface(Generator::class))) {
            return null;
        }
        return ['name' => $name, 'line' => $node->getStartLine()];
    }
}
