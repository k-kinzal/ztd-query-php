<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node>
 */
final class SqlFakerProviderDelegationRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            if ($scope->getNamespace() !== 'SqlFaker') {
                return [];
            }
        } else {
            $className = $class->getName();
            if (!str_starts_with($className, 'SqlFaker\\')) {
                return [];
            }
            if (str_contains(substr($className, strlen('SqlFaker\\')), '\\')) {
                return [];
            }
        }
        if ($node instanceof Property) {
            foreach ($node->props as $property) {
                if ($property->name->name === 'sql'
                    && (!$node->type instanceof \PhpParser\Node\Name
                        || !str_ends_with($scope->resolveName($node->type), '\\SqlGenerator'))
                ) {
                    return [$this->error($node->getStartLine())];
                }
            }

            return [];
        }
        if (!$node instanceof ClassMethod) {
            return [];
        }
        if ($node->name->name === '__construct') {
            return [];
        }
        foreach ($node->params as $parameter) {
            if ($parameter->type instanceof Node && $this->containsGenerationTargetType($parameter->type)) {
                return [$this->error($node->getStartLine())];
            }
        }
        $statements = $node->stmts ?? [];
        $statement = $statements[count($statements) - 1] ?? null;
        if ($statement instanceof Return_
            && $statement->expr !== null
            && $this->isApprovedDelegation($statement->expr)
        ) {
            return [];
        }

        return [$this->error($statement?->getStartLine() ?? $node->getStartLine())];
    }

    private function error(int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'SQLFaker Providers must delegate generation to SqlGenerator::generate() with a GenerationPlan.'
        )
            ->identifier('customRules.sqlFakerProviderDelegation')
            ->line($line)
            ->build();
    }

    private function isApprovedDelegation(Expr $expr): bool
    {
        if ($expr instanceof Match_) {
            foreach ($expr->arms as $arm) {
                if (!$this->isApprovedDelegation($arm->body)) {
                    return false;
                }
            }

            return $expr->arms !== [];
        }
        if (!$expr instanceof MethodCall || !$expr->name instanceof Identifier) {
            return false;
        }
        if ($expr->var instanceof Variable && $expr->var->name === 'this') {
            return $expr->name->name !== 'generateRequired';
        }
        if (!$expr->var instanceof PropertyFetch
            || !$expr->var->var instanceof Variable
            || !$expr->var->name instanceof Identifier
        ) {
            return false;
        }
        $arguments = $expr->getArgs();

        return $expr->var->var->name === 'this'
            && $expr->var->name->name === 'sql'
            && $expr->name->name === 'generate'
            && count($arguments) === 1
            && $this->isGenerationPlan($arguments[0]->value);
    }

    private function containsGenerationTargetType(Node $node): bool
    {
        if (($node instanceof Identifier || $node instanceof \PhpParser\Node\Name)
            && str_ends_with($node->toString(), 'GenerationTarget')
        ) {
            return true;
        }
        $subNodes = get_object_vars($node);
        foreach ($node->getSubNodeNames() as $name) {
            $child = $subNodes[$name] ?? null;
            if ($child instanceof Node && $this->containsGenerationTargetType($child)) {
                return true;
            }
            if (!is_array($child)) {
                continue;
            }
            foreach ($child as $item) {
                if ($item instanceof Node && $this->containsGenerationTargetType($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isGenerationPlan(Expr $expr): bool
    {
        if ($expr instanceof Variable) {
            return true;
        }
        if ($expr instanceof MethodCall) {
            return $this->isGenerationPlan($expr->var);
        }
        if (!$expr instanceof StaticCall || !$expr->class instanceof \PhpParser\Node\Name) {
            return false;
        }
        $class = $expr->class->toString();

        return str_ends_with($class, 'GenerationPlan') || str_ends_with($class, 'GenerationPlans');
    }
}
