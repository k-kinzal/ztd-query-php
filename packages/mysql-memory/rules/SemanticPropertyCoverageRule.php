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
use Tests\Generator\Inhabitant;

/**
 * Requires semantic property coverage and matching specialized generator names and types.
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
     * Checks specialized generators and runs the property inventory at the engine entry point.
     * Removing a property therefore cannot remove the coverage check itself.
     *
     * @param InClassNode $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        $declaration = $node->getClassReflection();
        foreach (['Tests\\Generator\\MySql\\' => 'SqlSemantics\\Platform\\MySql\\', 'Tests\\Generator\\Semantics\\' => 'SqlSemantics\\'] as $prefix => $modelPrefix) {
            $name = $declaration->getName();
            if (!str_starts_with($name, $prefix)) {
                continue;
            }
            $model = $modelPrefix . substr($name, strlen($prefix), -strlen('Generator'));
            if (!str_ends_with($name, 'Generator') || !$this->reflection->hasClass($model) || !$declaration->implementsInterface(Inhabitant::class)) {
                return [RuleErrorBuilder::message($name . ' must mirror a semantic type with a Generator suffix and implement ' . Inhabitant::class . '.')->identifier('mysqlMemory.invalidGenerator')->build()];
            }
            $returns = $declaration->getNativeMethod('sample')->getOnlyVariant()->getReturnType()->getObjectClassNames();
            return $returns === [$model] ? [] : [RuleErrorBuilder::message($name . '::sample() must return ' . $model . '.')->identifier('mysqlMemory.generatorReturn')->build()];
        }
        if ($declaration->getName() !== 'MySqlMemory\\Instance') {
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
                $errors[] = RuleErrorBuilder::message($property . ' must test ' . $value . ' with a public satisfies* method marked #[Test].')->identifier('mysqlMemory.invalidProperty')->build();
            }
        }
        return $errors;
    }

}
