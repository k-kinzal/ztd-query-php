<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
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
    private const STATEMENT_PATTERN = '~(?<![A-Za-z0-9_])(?:
        WITH\s+[A-Za-z_][A-Za-z0-9_]*\s+AS\s*\(
        | SELECT(?:
            \s{2,}FROM\b
            | \s+(?:
                \*
                | [0-9]+(?:\.[0-9]+)?
                | [\'"`(]
                | \$[0-9]+
                | :[A-Za-z_][A-Za-z0-9_]*
                | [A-Za-z_][A-Za-z0-9_.]*\s+(?:FROM|AS|,|\|\||[+*/-])
            )
        )
        | INSERT\s+INTO\b
        | UPDATE\s+[^\s,;)]+\s+SET\b
        | DELETE\s+FROM\b
        | CREATE\s+(?:TABLE|VIEW|INDEX|DATABASE|SCHEMA)\b
        | ALTER\s+(?:TABLE|VIEW|DATABASE|SCHEMA)\b
        | DROP\s+(?:TABLE|VIEW|INDEX|DATABASE|SCHEMA)\b
        | REPLACE\s+INTO\b
        | TRUNCATE(?:\s+TABLE)?\s+[^\s,;)]+
        | LOAD\s+DATA\b
        | MERGE\s+INTO\b
        | COPY\s+[^\s,;)]+\s+(?:FROM|TO)\b
        | (?:CALL|EXPLAIN|VACUUM|PRAGMA|ATTACH|DETACH|GRANT|REVOKE)\s+[^\s,;)]+
        | FROM\s+[^\s,;)]+\s+WHERE\b
        | JOIN\s+[^\s,;)]+\s+ON\b
        | WHERE\s+[^\s,;)]+\s*(?:=|<>|!=|<|>)
    )~ix';

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

        $className = $class->getName();
        if (!str_starts_with($className, 'SqlFaker\\')) {
            return [];
        }
        if (!$node instanceof String_
            && !$node instanceof InterpolatedStringPart
            && !$node instanceof InterpolatedString
            && !$node instanceof Concat
        ) {
            return [];
        }

        $value = $this->literalValue($node);
        if ($value === null) {
            return [];
        }
        if (!$this->containsSqlTemplate($value)) {
            return [];
        }

        return [RuleErrorBuilder::message(
            'SQLFaker must not construct SQL statements from fixed templates; derive them from the dialect grammar.'
        )
            ->identifier('customRules.noFixedSqlStatement')
            ->line($node->getStartLine())
            ->build()];
    }

    private function containsSqlTemplate(string $value): bool
    {
        $withoutBlockComments = preg_replace('~/\*.*?\*/~s', ' ', $value);
        if ($withoutBlockComments === null) {
            return false;
        }
        $withoutComments = preg_replace('/--[^\r\n]*/', ' ', $withoutBlockComments);

        return $withoutComments !== null && preg_match(self::STATEMENT_PATTERN, $withoutComments) === 1;
    }

    private function literalValue(Node $node): ?string
    {
        if ($node instanceof String_ || $node instanceof InterpolatedStringPart) {
            return $node->value;
        }
        if ($node instanceof InterpolatedString) {
            return implode('', array_map(
                static fn (Expr|InterpolatedStringPart $part): string => $part instanceof InterpolatedStringPart
                    ? $part->value
                    : '',
                $node->parts,
            ));
        }
        if (!$node instanceof Concat) {
            return null;
        }
        $left = $this->literalValue($node->left);
        $right = $this->literalValue($node->right);

        return $left === null || $right === null ? null : $left . $right;
    }
}
