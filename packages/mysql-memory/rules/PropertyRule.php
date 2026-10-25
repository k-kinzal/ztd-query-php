<?php

declare(strict_types=1);

namespace Rules;

use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Checks property declarations without imposing a one-property-per-model mapping.
 *
 * @implements Rule<InClassNode>
 * @visibility Rules
 */
final class PropertyRule implements Rule
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
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $name = $class->getName();
        if (!str_starts_with($name, 'Tests\\Property\\')) {
            return [];
        }
        if (!str_ends_with($name, 'Properties') || !$class->isSubclassOf(TestCase::class) || !(new RunnableProperty())->accepts($class)) {
            return [RuleErrorBuilder::message($name . ' must be a concrete *Properties test class with a public satisfies* method marked #[Test].')->identifier('mysqlMemory.invalidProperty')->build()];
        }
        return [];
    }
}
