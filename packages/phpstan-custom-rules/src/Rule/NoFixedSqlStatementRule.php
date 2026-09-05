<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignOp\Concat as AssignConcat;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node>
 */
final class NoFixedSqlStatementRule implements Rule
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
        $namespace = $scope->getNamespace() ?? '';
        if ($namespace !== 'SqlFaker' && !str_starts_with($namespace, 'SqlFaker\\')) {
            return [];
        }
        if ($node instanceof ClassMethod
            && str_starts_with($node->name->name, 'generate')
            && $this->isDialectGeneratorClass($scope)
        ) {
            return $this->invalidGeneratorReturnErrors($node, $scope);
        }
        if ($node instanceof Return_ && $node->expr !== null) {
            if ($this->isDialectGeneratorMethod($scope)) {
                return [];
            }
            foreach ($scope->getType($node->expr)->getConstantStrings() as $constantString) {
                $value = $constantString->getValue();
                if (SqlStatementTemplateDetector::contains($value, true)
                    && !SqlStatementTemplateDetector::contains($value)
                ) {
                    return [$this->error($node->getStartLine())];
                }
            }

            return [];
        }
        if (!$node instanceof String_
            && !$node instanceof InterpolatedStringPart
            && !$node instanceof InterpolatedString
            && !$node instanceof Concat
            && !$node instanceof Assign
            && !$node instanceof AssignConcat
            && !$node instanceof FuncCall
        ) {
            return [];
        }

        if ($node instanceof FuncCall) {
            foreach ($scope->getType($node)->getConstantStrings() as $constantString) {
                if ($this->containsSqlTemplate($constantString->getValue())) {
                    return [$this->error($node->getStartLine())];
                }
            }
        }

        $value = $this->literalValue($node);
        if ($value === null) {
            return [];
        }
        if (!$this->containsSqlTemplate($value)) {
            return [];
        }

        return [$this->error($node->getStartLine())];
    }

    private function isDialectGeneratorMethod(Scope $scope): bool
    {
        $function = $scope->getFunctionName();

        return $function !== null
            && str_starts_with($function, 'generate')
            && $this->isDialectGeneratorClass($scope);
    }

    private function isDialectGeneratorClass(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            return false;
        }
        $className = $class->getName();

        return strcmp($className, 'SqlFaker\\MySql\\SqlGenerator') === 0
            || strcmp($className, 'SqlFaker\\PostgreSql\\SqlGenerator') === 0
            || strcmp($className, 'SqlFaker\\Sqlite\\SqlGenerator') === 0;
    }

    /**
     * @param array<string, list<Expr>> $assignments
     * @param list<string> $visited
     */
    private function isApprovedGeneratorReturn(
        Expr $expr,
        Scope $scope,
        array $assignments,
        array $visited,
    ): bool {
        if ($expr instanceof Match_) {
            foreach ($expr->arms as $arm) {
                if (!$this->isApprovedGeneratorReturn($arm->body, $scope, $assignments, $visited)) {
                    return false;
                }
            }

            return $expr->arms !== [];
        }
        if ($expr instanceof Variable && is_string($expr->name)) {
            return $this->isGrammarDerivedVariable($expr->name, $assignments, $scope, $visited);
        }
        if (!$expr instanceof MethodCall || !$expr->name instanceof Identifier) {
            return false;
        }
        if ($expr->var instanceof Variable && $expr->var->name === 'this') {
            return str_starts_with($expr->name->name, 'generate');
        }
        if (!$expr->var instanceof PropertyFetch
            || !$expr->var->var instanceof Variable
            || $expr->var->var->name !== 'this'
            || !$expr->var->name instanceof Identifier
            || $expr->var->name->name !== 'lexicalGrammar'
        ) {
            return false;
        }

        if (str_starts_with($expr->name->name, 'generate')) {
            return true;
        }
        $argument = $expr->getArgs()[0]->value ?? null;

        return $expr->name->name === 'realize'
            && $argument instanceof Expr
            && $this->isDerivedTerminalExpression($argument, $assignments, $scope, []);
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function invalidGeneratorReturnErrors(ClassMethod $method, Scope $scope): array
    {
        $returns = [];
        $assignments = [];
        foreach ($method->stmts ?? [] as $statement) {
            $this->collectVariableFlow($statement, $returns, $assignments);
        }
        $errors = [];
        foreach ($returns as [$expression, $line]) {
            if ($this->isApprovedGeneratorReturn($expression, $scope, $assignments, [])) {
                continue;
            }
            $errors[] = $this->error($line);
        }

        return $errors;
    }

    /**
     * @param array<string, list<Expr>> $assignments
     * @param list<string> $visited
     */
    private function isGrammarDerivedVariable(
        string $name,
        array $assignments,
        Scope $scope,
        array $visited,
    ): bool {
        if (in_array($name, $visited, true)) {
            return false;
        }
        $sources = $assignments[$name] ?? [];
        if ($sources === []) {
            return false;
        }
        $visited[] = $name;
        foreach ($sources as $source) {
            if ($source instanceof Variable && is_string($source->name)) {
                if (!$this->isGrammarDerivedVariable($source->name, $assignments, $scope, $visited)) {
                    return false;
                }
                continue;
            }
            if (!$this->isApprovedGeneratorReturn($source, $scope, $assignments, $visited)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, list<Expr>> $assignments
     * @param list<string> $visited
     */
    private function isDerivedTerminalExpression(
        Expr $expr,
        array $assignments,
        Scope $scope,
        array $visited,
    ): bool {
        if ($expr instanceof Variable && is_string($expr->name)) {
            if (in_array($expr->name, $visited, true)) {
                return false;
            }
            $sources = $assignments[$expr->name] ?? [];
            if ($sources === []) {
                return false;
            }
            $visited[] = $expr->name;
            foreach ($sources as $source) {
                if (!$this->isDerivedTerminalExpression($source, $assignments, $scope, $visited)) {
                    return false;
                }
            }

            return true;
        }
        if ($expr instanceof FuncCall
            && $expr->name instanceof Name
            && strtolower($expr->name->toString()) === 'array_map'
        ) {
            $argument = $expr->getArgs()[1]->value ?? null;

            return $argument instanceof Expr
                && $this->isDerivedTerminalExpression($argument, $assignments, $scope, $visited);
        }
        if ($expr instanceof MethodCall
            && $expr->name instanceof Identifier
            && $expr->var instanceof New_
            && $expr->var->class instanceof Name
        ) {
            $class = $scope->resolveName($expr->var->class);
            if ($expr->name->name === 'of'
                && in_array($class, [
                    'SqlFaker\\Grammar\\Derivation',
                    'SqlFaker\\MySql\\Derivation',
                    'SqlFaker\\Sqlite\\Derivation',
                ], true)
            ) {
                return true;
            }
            if ($expr->name->name === 'applied'
                && in_array($class, [
                    'SqlFaker\\MySql\\ParserSemantics',
                    'SqlFaker\\PostgreSql\\ParserSemantics',
                ], true)
            ) {
                $argument = $expr->getArgs()[0]->value ?? null;

                return $argument instanceof Expr
                    && $this->isDerivedTerminalExpression($argument, $assignments, $scope, $visited);
            }
        }
        if (!$expr instanceof MethodCall
            || !$expr->var instanceof Variable
            || $expr->var->name !== 'this'
            || !$expr->name instanceof Identifier
        ) {
            return false;
        }
        if ($expr->name->name === 'derive') {
            return true;
        }
        if ($expr->name->name !== 'normalizeParserSemantics') {
            return false;
        }
        $argument = $expr->getArgs()[0]->value ?? null;

        return $argument instanceof Expr
            && $this->isDerivedTerminalExpression($argument, $assignments, $scope, $visited);
    }

    /**
     * @param list<array{Expr, int}> $returns
     * @param array<string, list<Expr>> $assignments
     */
    private function collectVariableFlow(Node $node, array &$returns, array &$assignments): void
    {
        if ($node instanceof \PhpParser\Node\FunctionLike && !$node instanceof ClassMethod) {
            return;
        }
        if ($node instanceof Return_ && $node->expr !== null) {
            $returns[] = [$node->expr, $node->getStartLine()];
        }
        if (($node instanceof Assign || $node instanceof AssignOp)
            && $node->var instanceof Variable
            && is_string($node->var->name)
        ) {
            $assignments[$node->var->name][] = $node->expr;
        }
        $subNodes = get_object_vars($node);
        foreach ($node->getSubNodeNames() as $name) {
            $child = $subNodes[$name] ?? null;
            if ($child instanceof Node) {
                $this->collectVariableFlow($child, $returns, $assignments);
                continue;
            }
            if (!is_array($child)) {
                continue;
            }
            foreach ($child as $item) {
                if ($item instanceof Node) {
                    $this->collectVariableFlow($item, $returns, $assignments);
                }
            }
        }
    }

    private function error(int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'SQLFaker must not construct SQL statements from fixed templates; derive them from the dialect grammar.'
        )
            ->identifier('customRules.noFixedSqlStatement')
            ->line($line)
            ->build();
    }

    private function containsSqlTemplate(string $value): bool
    {
        return SqlStatementTemplateDetector::contains($value);
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
                    : '__ztd_dynamic__',
                $node->parts,
            ));
        }
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $function = strtolower($node->name->toString());
            if (in_array($function, ['sprintf', 'vsprintf'], true)) {
                return $this->formattedValue($node, $function === 'vsprintf');
            }
            if ($function === 'str_replace') {
                $arguments = $node->getArgs();
                $search = isset($arguments[0]) ? $this->literalValue($arguments[0]->value) : null;
                $replace = isset($arguments[1]) ? $this->literalValue($arguments[1]->value) : null;
                $subject = isset($arguments[2]) ? $this->literalValue($arguments[2]->value) : null;

                return $search === null || $replace === null || $subject === null
                    ? null
                    : str_replace($search, $replace, $subject);
            }
            if (!in_array($function, ['implode', 'join'], true)) {
                return null;
            }
            $arguments = $node->getArgs();
            $separatorNode = count($arguments) === 1 ? null : ($arguments[0]->value ?? null);
            $valuesNode = count($arguments) === 1
                ? ($arguments[0]->value ?? null)
                : ($arguments[1]->value ?? null);
            if (!$valuesNode instanceof Array_) {
                return null;
            }
            $separator = $separatorNode === null ? '' : ($this->literalValue($separatorNode) ?? ' ');
            $values = [];
            foreach ($valuesNode->items as $item) {
                $values[] = $this->literalValue($item->value) ?? '__ztd_dynamic__';
            }

            return implode($separator, $values);
        }
        if ($node instanceof AssignConcat) {
            $suffix = $this->literalValue($node->expr);

            return $suffix === null ? null : 'SELECT __ztd_dynamic__ ' . $suffix;
        }
        if ($node instanceof Assign
            && $node->var instanceof Variable
            && is_string($node->var->name)
            && $node->expr instanceof Concat
            && $node->expr->left instanceof Variable
            && $node->expr->left->name === $node->var->name
        ) {
            $suffix = $this->literalValue($node->expr->right);

            return $suffix === null ? null : 'SELECT __ztd_dynamic__ ' . $suffix;
        }
        if (!$node instanceof Concat) {
            return null;
        }
        $left = $this->literalValue($node->left) ?? '__ztd_dynamic__';
        $right = $this->literalValue($node->right) ?? '__ztd_dynamic__';

        return $left . $right;
    }

    private function formattedValue(FuncCall $call, bool $argumentsInArray): ?string
    {
        $arguments = $call->getArgs();
        $formatNode = $arguments[0]->value ?? null;
        if (!$formatNode instanceof Node) {
            return null;
        }
        $format = $this->literalValue($formatNode);
        if ($format === null) {
            return null;
        }
        if ($this->containsSqlTemplate($format)) {
            return null;
        }
        if ($argumentsInArray) {
            $valuesNode = $arguments[1]->value ?? null;
            if (!$valuesNode instanceof Array_) {
                return null;
            }
            $valueNodes = array_map(static fn ($item): Node => $item->value, $valuesNode->items);
        } else {
            $valueNodes = array_map(static fn ($argument): Node => $argument->value, array_slice($arguments, 1));
        }
        $values = array_map(
            fn (Node $value): string => $this->literalValue($value) ?? '__ztd_dynamic__',
            $valueNodes,
        );
        $index = 0;
        $formatted = preg_replace_callback(
            '~%(?:[0-9]+\\$)?[-+0\x20\x27]*(?:[0-9]+|\*)?(?:\.(?:[0-9]+|\*))?[bcdeEfFgGosuxX]~',
            static function () use (&$index, $values): string {
                return $values[$index++] ?? '';
            },
            $format,
        );

        return $formatted;
    }
}
