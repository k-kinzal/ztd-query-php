<?php

declare(strict_types=1);

namespace Rules;

use Eris\Generator;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Requires a named Eris generator for each public semantic value type.
 *
 * @implements Rule<CollectedDataNode>
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
     * @return class-string<CollectedDataNode>
     */
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * Checks the whole source inventory after collection, without a runtime sentinel.
     * Partial file analysis cannot establish package-wide generator coverage.
     *
     * @param CollectedDataNode $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        if ($node->isOnlyFilesAnalysis()) {
            return [];
        }
        $models = $this->catalogue->generators();
        $declarations = [];
        $errors = [];
        foreach ($node->get(GeneratorDeclarationCollector::class) as $file => $classes) {
            foreach ($classes as $class) {
                $declarations[$class['name']] = ['file' => $file, 'line' => $class['line']];
                if (!in_array($class['name'], $models, true)) {
                    $errors[] = RuleErrorBuilder::message($class['name'] . ' must mirror a public SQL Semantics value type with a Generator suffix.')
                        ->identifier('mysqlMemory.unmappedGenerator')->file($file)->line($class['line'])->build();
                }
            }
        }
        foreach ($models as $model => $generator) {
            $location = $declarations[$generator] ?? $this->catalogue->location($model);
            if (!$this->reflection->hasClass($generator)) {
                $errors[] = RuleErrorBuilder::message($model . ' requires ' . $generator . '.')
                    ->identifier('mysqlMemory.missingGenerator')->file($location['file'])->line($location['line'])->build();
                continue;
            }
            $class = $this->reflection->getClass($generator);
            $generated = $class->getAncestorWithClassName(Generator::class)?->getActiveTemplateTypeMap()->getType('T');
            if ($generated instanceof \PHPStan\Type\Generic\TemplateType) {
                $generated = $generated->getBound();
            }
            if (!$class->getNativeReflection()->isInstantiable() || $generated === null || !$generated->equals(new ObjectType($model))) {
                $errors[] = RuleErrorBuilder::message($generator . ' must be instantiable and implement Eris\\Generator<' . $model . '>.')
                    ->identifier('mysqlMemory.invalidGenerator')->file($location['file'])->line($location['line'])->build();
            }
        }
        return $errors;
    }
}
