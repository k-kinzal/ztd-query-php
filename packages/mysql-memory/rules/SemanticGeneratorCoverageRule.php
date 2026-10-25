<?php

declare(strict_types=1);

namespace Rules;

use Eris\Generator;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Requires a named Eris generator for each public semantic value type.
 *
 * @implements Rule<InClassNode>
 * @visibility Rules
 */
final class SemanticGeneratorCoverageRule implements Rule
{
    /**
     * Shares the dependency inventory across declarations in an analysis worker.
     */
    public function __construct(private readonly ReflectionProvider $reflection, private readonly SemanticCatalogue $catalogue)
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
     * Checks existence independently of generator declarations, then checks each declared generator's type.
     *
     * @param InClassNode $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $name = $class->getName();
        if ($name === 'MySqlMemory\\Instance') {
            $errors = [];
            foreach ($this->catalogue->generators() as $model => $generator) {
                if (!$this->reflection->hasClass($generator)) {
                    $errors[] = RuleErrorBuilder::message($model . ' requires ' . $generator . '.')->identifier('mysqlMemory.missingGenerator')->build();
                }
            }
            return $errors;
        }
        if (!str_starts_with($name, 'Tests\\Generator\\') || (!str_ends_with($name, 'Generator') && !$class->implementsInterface(Generator::class))) {
            return [];
        }
        $model = array_search($name, $this->catalogue->generators(), true);
        if ($model === false) {
            return [RuleErrorBuilder::message($name . ' must mirror a public SQL Semantics value type with a Generator suffix.')->identifier('mysqlMemory.unmappedGenerator')->build()];
        }
        $generated = $class->getAncestorWithClassName(Generator::class)?->getActiveTemplateTypeMap()->getType('T');
        if (!$class->getNativeReflection()->isInstantiable() || $generated === null || !$generated->equals(new ObjectType($model))) {
            return [RuleErrorBuilder::message($name . ' must be instantiable and implement Eris\\Generator<' . $model . '>.')->identifier('mysqlMemory.invalidGenerator')->build()];
        }
        return [];
    }
}
