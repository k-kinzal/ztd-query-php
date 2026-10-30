<?php

declare(strict_types=1);

namespace Rules;

use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Keeps every generator under Tests\Generator in one shape: a final *Generator class implementing
 * Eris\Generator whose constructor composes a delegate from the generators of the type's operands,
 * with __invoke and shrink forwarding to that delegate and no other method.
 *
 * @implements Rule<InClassNode>
 * @visibility Rules
 */
final class GeneratorShapeRule implements Rule
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
        if (!str_starts_with($name, 'Tests\\Generator\\') || $class->isTrait() || $class->isInterface()) {
            return [];
        }
        $errors = [];
        if (!str_ends_with($name, 'Generator') || !$class->isFinal() || !$class->implementsInterface('Eris\\Generator')) {
            $errors[] = RuleErrorBuilder::message($name . ' must be a final *Generator class implementing Eris\\Generator.')->identifier('mysqlMemory.generatorShape')->build();
        }
        $declared = [];
        foreach ($class->getNativeReflection()->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $name) {
                $declared[] = $method->getName();
            }
        }
        foreach (array_diff($declared, ['__construct', '__invoke', 'shrink']) as $method) {
            $errors[] = RuleErrorBuilder::message($name . '::' . $method . '() is not part of a generator: compose the delegate in the constructor and keep only __invoke() and shrink().')->identifier('mysqlMemory.generatorShape')->build();
        }
        foreach (array_diff(['__invoke', 'shrink'], $declared) as $method) {
            $errors[] = RuleErrorBuilder::message($name . ' must declare ' . $method . '() forwarding to its delegate.')->identifier('mysqlMemory.generatorShape')->build();
        }

        return $errors;
    }
}
