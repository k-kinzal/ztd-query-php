<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PhpParser\Node;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node>
 */
final class NoFixedSqlStatementRule implements Rule
{
    /** @var list<string> */
    private const CLASSES = [
        'MySqlProvider',
        'PostgreSqlProvider',
        'SqliteProvider',
        'SqlGenerator',
    ];

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
            return [];
        }

        $separator = strrpos($class->getName(), '\\');
        $className = $separator === false ? $class->getName() : substr($class->getName(), $separator + 1);
        if (!in_array($className, self::CLASSES, true)) {
            return [];
        }

        if (!$node instanceof String_ && !$node instanceof InterpolatedStringPart && !$node instanceof InterpolatedString) {
            return [];
        }

        $value = $node instanceof InterpolatedString
            ? implode('', array_map(
                static fn (Node\Expr|InterpolatedStringPart $part): string => $part instanceof InterpolatedStringPart ? $part->value : '',
                $node->parts,
            ))
            : $node->value;
        if (preg_match('/\b(?:SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|REPLACE|TRUNCATE|LOAD|MERGE|DO)\s/i', $value) !== 1) {
            return [];
        }

        return [RuleErrorBuilder::message(
            'SQLFaker Providers and SqlGenerators must not construct SQL statements from fixed templates; derive them from the dialect grammar.'
        )
            ->identifier('customRules.noFixedSqlStatement')
            ->line($node->getStartLine())
            ->build()];
    }
}
