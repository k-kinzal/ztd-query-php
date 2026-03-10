<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use ZtdQuery\PhpStanCustomRules\Support\ContractSymbolUseIndex;

/**
 * @implements Rule<FileNode>
 */
final class ContractSymbolMustBeUsedRule implements Rule
{
    private const CONTRACT_MARKER = '/src/Contract/';
    private const CONTRACT_NAMESPACE_PREFIX = 'SqlFaker\\Contract';
    private readonly ContractSymbolUseIndex $useIndex;

    public function __construct()
    {
        $this->useIndex = new ContractSymbolUseIndex();
    }

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @param FileNode $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        $file = $this->normalizePath($scope->getFile());
        $contractSplit = $this->splitContractPath($file);
        if ($contractSplit === null) {
            return [];
        }

        [$packageRoot] = $contractSplit;
        $imports = $this->useIndex->load($packageRoot);
        $errors = [];

        foreach ($this->contractDeclarations($node->getNodes()) as [$symbol, $line]) {
            if (isset($imports[$symbol])) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Contract symbol "%s" must be imported by at least one non-contract SqlFaker source file using a use statement.',
                $symbol,
            ))
                ->identifier('customRules.unusedContractSymbol')
                ->line($line)
                ->build();
        }

        return $errors;
    }

    /**
     * @param array<array-key, Node> $statements
     * @return list<array{string, int}>
     */
    private function contractDeclarations(array $statements): array
    {
        $declarations = [];

        foreach ($statements as $statement) {
            if (!$statement instanceof Namespace_) {
                continue;
            }

            $namespace = $statement->name?->toString() ?? '';
            if ($namespace !== self::CONTRACT_NAMESPACE_PREFIX && !str_starts_with($namespace, self::CONTRACT_NAMESPACE_PREFIX . '\\')) {
                continue;
            }

            foreach ($statement->stmts as $namespaceStatement) {
                if ($namespaceStatement instanceof Function_) {
                    $declarations[] = [$namespace . '\\' . $namespaceStatement->name->toString(), $namespaceStatement->getLine()];
                    continue;
                }

                if ($namespaceStatement instanceof Const_) {
                    foreach ($namespaceStatement->consts as $const) {
                        $declarations[] = [$namespace . '\\' . $const->name->toString(), $const->getLine()];
                    }
                    continue;
                }

                if ($namespaceStatement instanceof ClassLike) {
                    $name = $namespaceStatement->name?->toString();
                    if ($name !== null) {
                        $declarations[] = [$namespace . '\\' . $name, $namespaceStatement->getLine()];
                    }
                }
            }
        }

        return $declarations;
    }

    /**
     * @return array{string, string}|null
     */
    private function splitContractPath(string $path): ?array
    {
        $position = strpos($path, self::CONTRACT_MARKER);
        if ($position === false) {
            return null;
        }

        $packageRoot = substr($path, 0, $position);
        $relativePath = substr($path, $position + strlen(self::CONTRACT_MARKER));

        return [$packageRoot, $relativePath];
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
