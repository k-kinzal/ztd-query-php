<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use ZtdQuery\PhpStanCustomRules\Support\TestClassScope;

/**
 * @implements Rule<\PhpParser\Node\Stmt\ClassMethod>
 */
final class NoPrivateMethodInTestClassRule implements Rule
{
    public function getNodeType(): string
    {
        return \PhpParser\Node\Stmt\ClassMethod::class;
    }

    /**
     * @param \PhpParser\Node\Stmt\ClassMethod $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        if (!$node->isPrivate()) {
            return [];
        }

        if (!TestClassScope::isRestrictedTestClass($scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Private methods are prohibited in Tests\\Unit and Tests\\Integration classes. Over-abstracted helpers hide test intent and make failures harder to understand. Inline the logic into each test method, or extract to a dedicated helper class if reuse is truly needed.'
            )
                ->identifier('customRules.testClassPrivateMethod')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
