<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<\PhpParser\Node\Expr>
 */
final class ForbiddenMagicMethodCallRule implements Rule
{
    /**
     * @var list<string>
     */
    private const MAGIC_METHODS = [
        '__construct',
        '__destruct',
        '__call',
        '__callStatic',
        '__get',
        '__set',
        '__isset',
        '__unset',
        '__sleep',
        '__wakeup',
        '__serialize',
        '__unserialize',
        '__toString',
        '__invoke',
        '__set_state',
        '__clone',
        '__debugInfo',
    ];

    public function getNodeType(): string
    {
        return \PhpParser\Node\Expr::class;
    }

    /**
     * @param \PhpParser\Node\Expr $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        unset($scope);

        if ($node instanceof \PhpParser\Node\Expr\MethodCall) {
            return $this->checkMethodCall($node);
        }

        if ($node instanceof \PhpParser\Node\Expr\StaticCall) {
            return $this->checkStaticCall($node);
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function checkMethodCall(\PhpParser\Node\Expr\MethodCall $node): array
    {
        if (!$node->name instanceof \PhpParser\Node\Identifier) {
            return [];
        }

        $methodName = $node->name->toString();
        if (!$this->isMagicMethod($methodName)) {
            return [];
        }

        if ($this->isParentCall($node)) {
            return [];
        }

        return [$this->buildError($methodName, $node->getStartLine())];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function checkStaticCall(\PhpParser\Node\Expr\StaticCall $node): array
    {
        if (!$node->name instanceof \PhpParser\Node\Identifier) {
            return [];
        }

        $methodName = $node->name->toString();
        if (!$this->isMagicMethod($methodName)) {
            return [];
        }

        if ($this->isParentStaticCall($node)) {
            return [];
        }

        return [$this->buildError($methodName, $node->getStartLine())];
    }

    private function isMagicMethod(string $methodName): bool
    {
        return in_array($methodName, self::MAGIC_METHODS, true);
    }

    private function isParentCall(\PhpParser\Node\Expr\MethodCall $node): bool
    {
        return false;
    }

    private function isParentStaticCall(\PhpParser\Node\Expr\StaticCall $node): bool
    {
        return $node->class instanceof \PhpParser\Node\Name
            && strtolower($node->class->toString()) === 'parent';
    }

    private function buildError(string $methodName, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            sprintf(
                'Direct call to magic method %s() is prohibited. Magic methods are invoked implicitly by PHP; calling them directly bypasses language semantics. Use the corresponding language construct instead (e.g. (string)$obj instead of $obj->__toString()).',
                $methodName
            )
        )
            ->identifier('customRules.forbiddenMagicMethodCall')
            ->line($line)
            ->build();
    }
}
