<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FileNode>
 */
final class SrcUnitTestPairRule implements Rule
{
    private const SRC_MARKER = '/src/';
    private const UNIT_TEST_MARKER = '/tests/Unit/';

    /**
     * @param list<string> $excludePatterns
     */
    public function __construct(
        private readonly array $excludePatterns = []
    ) {
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
        unset($node);

        $file = $this->normalizePath($scope->getFile());
        if (!str_ends_with($file, '.php')) {
            return [];
        }

        $srcSplit = $this->splitPathByMarker($file, self::SRC_MARKER);
        if ($srcSplit !== null) {
            [$packageRoot, $srcRelativePath] = $srcSplit;

            if ($this->isExcluded(basename($file))) {
                return [];
            }

            $expectedTestRelativePath = $this->toUnitTestRelativePath($srcRelativePath);
            $expectedTestPath = $packageRoot . self::UNIT_TEST_MARKER . $expectedTestRelativePath;

            if (!is_file($expectedTestPath)) {
                return [
                    RuleErrorBuilder::message(sprintf(
                        'Source file "src/%s" requires a matching unit test file "tests/Unit/%s" to keep behavior verifiable.',
                        $srcRelativePath,
                        $expectedTestRelativePath
                    ))
                        ->identifier('customRules.srcWithoutUnitTest')
                        ->line(1)
                        ->build(),
                ];
            }

            return [];
        }

        $testSplit = $this->splitPathByMarker($file, self::UNIT_TEST_MARKER);
        if ($testSplit !== null) {
            [$packageRoot, $testRelativePath] = $testSplit;
            $expectedSourceRelativePath = $this->toSourceRelativePath($testRelativePath);
            $expectedSourcePath = $packageRoot . self::SRC_MARKER . $expectedSourceRelativePath;

            if (!is_file($expectedSourcePath)) {
                return [
                    RuleErrorBuilder::message(sprintf(
                        'Unit test file "tests/Unit/%s" requires a matching source file "src/%s" to avoid stale or orphaned tests.',
                        $testRelativePath,
                        $expectedSourceRelativePath
                    ))
                        ->identifier('customRules.unitTestWithoutSource')
                        ->line(1)
                        ->build(),
                ];
            }
        }

        return [];
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * @return array{string, string}|null
     */
    private function splitPathByMarker(string $path, string $marker): ?array
    {
        $pos = strpos($path, $marker);
        if ($pos === false) {
            return null;
        }

        $root = substr($path, 0, $pos);
        $relativePath = substr($path, $pos + strlen($marker));

        return [$root, $relativePath];
    }

    private function toUnitTestRelativePath(string $srcRelativePath): string
    {
        $basePath = substr($srcRelativePath, 0, -4);
        return $basePath . 'Test.php';
    }

    private function toSourceRelativePath(string $testRelativePath): string
    {
        $withoutPhp = substr($testRelativePath, 0, -4);
        if (str_ends_with($withoutPhp, 'Test')) {
            $withoutPhp = substr($withoutPhp, 0, -4);
        }

        return $withoutPhp . '.php';
    }

    private function isExcluded(string $basename): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (fnmatch($pattern, $basename)) {
                return true;
            }
        }

        return false;
    }
}
