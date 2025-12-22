<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use ZtdQuery\PhpStanCustomRules\Support\TestClassScope;

/**
 * @implements Rule<\PhpParser\Node\Stmt\ClassConst>
 */
final class NoClassConstantInTestClassRule implements Rule
{
    public function getNodeType(): string
    {
        return \PhpParser\Node\Stmt\ClassConst::class;
    }

    /**
     * @param \PhpParser\Node\Stmt\ClassConst $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        if (!TestClassScope::isRestrictedTestClass($scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Class constants are prohibited in Tests\\Unit and Tests\\Integration classes. Shared constants encourage fixture coupling and reduce test independence. Use inline literal values in each test method instead.'
            )
                ->identifier('customRules.testClassConstant')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
