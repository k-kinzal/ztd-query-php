<?php

declare(strict_types=1);

namespace Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * Selects declarations by fully qualified name, with * matching any sequence.
 *
 * @implements Collector<InClassNode, array{name: string, line: int}>
 * @visibility Rules
 */
final class ClassDeclarationCollector implements Collector
{
    /**
     * @param list<string> $classes The case-insensitive class-name patterns to check
     */
    public function __construct(private readonly array $classes)
    {
    }

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
        if ($class->isAnonymous()) {
            return null;
        }
        $name = $class->getName();
        foreach ($this->classes as $pattern) {
            $expression = '~^' . str_replace('\\*', '.*', preg_quote(ltrim($pattern, '\\'), '~')) . '$~iD';
            if (preg_match($expression, $name) === 1) {
                return ['name' => $name, 'line' => $node->getStartLine()];
            }
        }
        return null;
    }
}
