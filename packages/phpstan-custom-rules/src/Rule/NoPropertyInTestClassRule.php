<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use ZtdQuery\PhpStanCustomRules\Support\TestClassScope;

/**
 * @implements Rule<\PhpParser\Node\Stmt\Property>
 */
final class NoPropertyInTestClassRule implements Rule
{
    public function getNodeType(): string
    {
        return \PhpParser\Node\Stmt\Property::class;
    }

    /**
     * @param \PhpParser\Node\Stmt\Property $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        if (!TestClassScope::isRestrictedTestClass($scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Properties are prohibited in Tests\\Unit and Tests\\Integration classes. Shared state across test methods reduces test isolation and makes failures harder to debug. Declare values as local variables inside each test method instead.'
            )
                ->identifier('customRules.testClassProperty')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
