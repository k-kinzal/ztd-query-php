<?php

declare(strict_types=1);

namespace Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports selected classes unreachable from references outside the selection.
 * Self references and disconnected cycles do not make selected classes used.
 *
 * @implements Rule<CollectedDataNode>
 * @visibility Rules
 */
final class UnusedClassRule implements Rule
{
    /**
     * @return class-string<CollectedDataNode>
     */
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * Analyse all relevant directories; isolated file checks cannot establish non-use.
     *
     * @param CollectedDataNode $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->isOnlyFilesAnalysis()) {
            return [];
        }
        $declarations = [];
        foreach ($node->get(ClassDeclarationCollector::class) as $file => $classes) {
            foreach ($classes as $class) {
                $declarations[strtolower($class['name'])] = $class + ['file' => $file];
            }
        }
        $edges = [];
        $pending = [];
        foreach ($node->get(ClassUsageCollector::class) as $usages) {
            foreach ($usages as $usage) {
                $from = $usage['from'] === null ? '' : strtolower($usage['from']);
                foreach ($usage['to'] as $name) {
                    $to = strtolower(ltrim($name, '\\'));
                    if (!isset($declarations[$to])) {
                        continue;
                    }
                    if (isset($declarations[$from])) {
                        $edges[$from][$to] = true;
                    } else {
                        $pending[] = $to;
                    }
                }
            }
        }
        $used = [];
        while ($pending !== []) {
            $name = array_pop($pending);
            if (isset($used[$name])) {
                continue;
            }
            $used[$name] = true;
            $pending = array_merge($pending, array_keys($edges[$name] ?? []));
        }
        $errors = [];
        foreach ($declarations as $name => $class) {
            if (!isset($used[$name])) {
                $errors[] = RuleErrorBuilder::message('Unused class ' . $class['name'] . ': no reference from outside the configured classes.')
                    ->identifier('customRules.unusedClass')->file($class['file'])->line($class['line'])->build();
            }
        }
        return $errors;
    }
}
