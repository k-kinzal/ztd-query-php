<?php

declare(strict_types=1);

namespace Rules;

use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Requires a runnable property class for every public value in both semantic packages.
 *
 * @implements Rule<InClassNode>
 * @visibility Rules
 */
final class SemanticPropertyCoverageRule implements Rule
{
    /**
     * Uses PHPStan's source reflection, including properties that have not been executed yet.
     */
    public function __construct(private readonly ReflectionProvider $reflection)
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
     * Runs once for the engine entry point, so removing a property does not remove the check.
     *
     * @param InClassNode $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        if ($node->getClassReflection()->getName() !== 'MySqlMemory\\Instance') {
            return [];
        }
        $errors = [];
        foreach ((new SemanticCatalogue())->properties() as $value => $property) {
            if (!$this->reflection->hasClass($property)) {
                $errors[] = RuleErrorBuilder::message($value . ' requires ' . $property . '.')->identifier('mysqlMemory.missingProperty')->build();
                continue;
            }
            $class = $this->reflection->getClass($property);
            $subjects = $class->hasConstant('MODEL') ? $class->getConstant('MODEL')->getValueType()->getConstantStrings() : [];
            if (!$class->isSubclassOf(TestCase::class) || count($subjects) !== 1 || $subjects[0]->getValue() !== $value || !(new RunnableProperty())->accepts($class)) {
                $errors[] = RuleErrorBuilder::message($property . ' must test ' . $value . ' with satisfiesPublicValueInvariants().')->identifier('mysqlMemory.invalidProperty')->build();
            }
        }
        return $errors;
    }

}
