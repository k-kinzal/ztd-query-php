<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use ZtdQuery\PhpStanCustomRules\Support\TestClassScope;

/**
 * @implements Rule<\PhpParser\Node\Stmt\TraitUse>
 */
final class NoTraitUseInTestClassRule implements Rule
{
    public function getNodeType(): string
    {
        return \PhpParser\Node\Stmt\TraitUse::class;
    }

    /**
     * @param \PhpParser\Node\Stmt\TraitUse $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        if (!TestClassScope::isRestrictedTestClass($scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Trait usage is prohibited in Tests\\Unit and Tests\\Integration classes. Traits can circumvent test class restrictions (no properties, no constants, no private methods). Move shared behavior to a dedicated helper class and call it explicitly.'
            )
                ->identifier('customRules.testClassTraitUse')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
