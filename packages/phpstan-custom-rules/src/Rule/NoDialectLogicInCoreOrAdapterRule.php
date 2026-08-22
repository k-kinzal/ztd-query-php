<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node>
 */
final class NoDialectLogicInCoreOrAdapterRule implements Rule
{
    /** @var list<string> */
    private const DIALECT_NAMESPACES = [
        'ZtdQuery\\Platform\\MySql\\',
        'ZtdQuery\\Platform\\Postgres\\',
        'ZtdQuery\\Platform\\Sqlite\\',
    ];

    /** @var list<string> */
    private const DIALECT_METADATA_LITERALS = [
        'mysql',
        'pgsql',
        'sqlite',
        'native_type',
        'sqlite:decl_type',
        'pdo_type',
        'flags',
        'precision',
        'driver',
        'charsetnr',
        'longlong',
        'newdecimal',
        'var_string',
        'bpchar',
        'int4',
        'float8',
        'bytea',
        'jsonb',
        'timestamptz',
        'datetime2',
    ];

    private const SQL_RENDERING_PATTERN = '~(?:
        \bROW_NUMBER\s*\(
        | \bCAST\s*\(
        | ^\s*(?:WITH|SELECT|FROM|WHERE|JOIN|AS)\s+$
        | ^\s*NULL\s*$
        | \bWITH\s+[^\s]+\s+AS\s*\(
        | \bSELECT\s+.+\s+FROM\b
        | \bINSERT\s+INTO\b
        | \bUPDATE\s+[^\s]+\s+SET\b
        | \bDELETE\s+FROM\b
        | \b(?:CREATE|ALTER|DROP)\s+(?:TABLE|VIEW|INDEX|DATABASE|SCHEMA)\b
    )~ix';

    /** @var list<string> */
    private const NATIVE_TYPE_NAMES = [
        'TINYINT',
        'SMALLINT',
        'INTEGER',
        'BIGINT',
        'MEDIUMINT',
        'YEAR',
        'BIT',
        'FLOAT',
        'DOUBLE',
        'DECIMAL',
        'DATE',
        'TIME',
        'DATETIME',
        'TIMESTAMP',
        'JSON',
        'BLOB',
        'TEXT',
        'VARCHAR',
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            return [];
        }
        $className = $class->getName();
        $pdoAdapter = str_starts_with($className, 'ZtdQuery\\Adapter\\Pdo\\');
        $mysqliAdapter = str_starts_with($className, 'ZtdQuery\\Adapter\\Mysqli\\');
        $adapter = $pdoAdapter || $mysqliAdapter;
        $compositionRoot = strcmp($className, 'ZtdQuery\\Adapter\\Pdo\\ZtdPdo') === 0
            || strcmp($className, 'ZtdQuery\\Adapter\\Mysqli\\ZtdMysqli') === 0;
        $core = str_starts_with($className, 'ZtdQuery\\')
            && !$adapter
            && !str_starts_with($className, 'ZtdQuery\\PhpStanCustomRules\\')
            && !$this->isDialectClass($className);
        if (!$adapter && !$core) {
            return [];
        }

        if (!$compositionRoot
            && $node instanceof Name
            && ($this->isDialectClass(ltrim($scope->resolveName($node), '\\'))
                || $this->isDialectClass(ltrim($node->toString(), '\\')))
        ) {
            return $this->error($node);
        }
        if ($adapter && $node instanceof StaticCall
            && $node->class instanceof Name
            && $scope->resolveName($node->class) === 'ZtdQuery\\Sql\\SqlTokenStream'
        ) {
            return $this->error($node);
        }
        if ($mysqliAdapter && $node instanceof Name
            && str_starts_with($node->toString(), 'MYSQLI_TYPE_')
        ) {
            return $this->error($node);
        }
        if ($mysqliAdapter && $this->isNumericMysqliTypeInterpretation($node)) {
            return $this->error($node);
        }

        $literal = $this->literalValue($node);
        if ($literal === null) {
            return [];
        }
        $normalized = strtolower($literal);
        if ($compositionRoot
            && (in_array($normalized, ['mysql', 'pgsql', 'sqlite'], true)
                || $this->isDialectClass(ltrim($literal, '\\')))
        ) {
            return [];
        }
        if ($this->isDialectClass(ltrim($literal, '\\'))
            || in_array($normalized, self::DIALECT_METADATA_LITERALS, true)
            || preg_match(self::SQL_RENDERING_PATTERN, $literal) === 1
        ) {
            return $this->error($node);
        }

        return [];
    }

    private function isDialectClass(string $className): bool
    {
        foreach (self::DIALECT_NAMESPACES as $namespace) {
            if (str_starts_with($className, $namespace)) {
                return true;
            }
        }

        return false;
    }

    private function isNumericMysqliTypeInterpretation(Node $node): bool
    {
        if ($node instanceof Match_) {
            $nativeMappings = 0;
            foreach ($node->arms as $arm) {
                foreach ($arm->conds ?? [] as $condition) {
                    if ($condition instanceof Int_
                        && $arm->body instanceof String_
                        && in_array(strtoupper($arm->body->value), self::NATIVE_TYPE_NAMES, true)
                    ) {
                        $nativeMappings++;
                    }
                    if ($this->isTypeExpression($node->cond) && $condition instanceof Int_) {
                        return true;
                    }
                }
            }

            return $nativeMappings >= 2;
        }
        if ($node instanceof BinaryOp) {
            return ($node->left instanceof Int_ && $this->isTypeExpression($node->right))
                || ($node->right instanceof Int_ && $this->isTypeExpression($node->left));
        }
        if (!$node instanceof Array_) {
            return false;
        }
        $nativeMappings = 0;
        foreach ($node->items as $item) {
            if (!$item->key instanceof Int_ || !$item->value instanceof String_) {
                continue;
            }
            if (in_array(strtoupper($item->value->value), self::NATIVE_TYPE_NAMES, true)) {
                $nativeMappings++;
            }
        }

        return $nativeMappings >= 2;
    }

    private function isTypeExpression(Expr $expr): bool
    {
        if ($expr instanceof Variable) {
            return $expr->name === 'type' || $expr->name === 'fieldType';
        }
        if ($expr instanceof PropertyFetch && $expr->name instanceof Identifier) {
            return $expr->name->name === 'type' || $expr->name->name === 'fieldType';
        }
        if (!$expr instanceof ArrayDimFetch || !$expr->dim instanceof String_) {
            return false;
        }

        return $expr->dim->value === 'type';
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

    /** @return list<IdentifierRuleError> */
    private function error(Node $node): array
    {
        return [RuleErrorBuilder::message(
            'Core and database adapters must delegate dialect-specific parsing, rendering, and metadata interpretation through platform contracts.'
        )
            ->identifier('customRules.noDialectLogicInCoreOrAdapter')
            ->line($node->getStartLine())
            ->build()];
    }
}
