<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Support;

use PhpParser\Node;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ContractSymbolUseIndex
{
    private const ROOT_NAMESPACE = 'SqlFaker';
    private const CONTRACT_NAMESPACE_PREFIX = 'SqlFaker\\Contract\\';

    /** @var array<string, array<string, true>> */
    private static array $cache = [];

    /**
     * @return array<string, true>
     */
    public function load(string $packageRoot): array
    {
        $normalizedRoot = $this->normalizePath($packageRoot);
        if (isset(self::$cache[$normalizedRoot])) {
            return self::$cache[$normalizedRoot];
        }

        return self::$cache[$normalizedRoot] = $this->buildIndex($normalizedRoot);
    }

    /**
     * @return array<string, true>
     */
    private function buildIndex(string $packageRoot): array
    {
        $srcRoot = $packageRoot . '/src';
        if (!is_dir($srcRoot)) {
            return [];
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $imports = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcRoot, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $this->normalizePath($file->getPathname());
            if (str_contains($path, '/src/Contract/')) {
                continue;
            }

            $code = file_get_contents($path);
            if ($code === false) {
                continue;
            }

            $statements = $parser->parse($code);
            if ($statements === null) {
                continue;
            }

            foreach ($statements as $statement) {
                if (!$statement instanceof Namespace_) {
                    continue;
                }

                $namespace = $statement->name?->toString() ?? '';
                if (
                    $namespace !== self::ROOT_NAMESPACE
                    && !str_starts_with($namespace, self::ROOT_NAMESPACE . '\\')
                ) {
                    continue;
                }

                if ($namespace === 'SqlFaker\\Contract' || str_starts_with($namespace, self::CONTRACT_NAMESPACE_PREFIX)) {
                    continue;
                }

                $this->collectImports($statement->stmts, $imports);
            }
        }

        return $imports;
    }

    /**
     * @param array<array-key, Node\Stmt> $statements
     * @param array<string, true> $imports
     */
    private function collectImports(array $statements, array &$imports): void
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Use_) {
                $this->collectUseStatement($statement, '', $imports);
                continue;
            }

            if ($statement instanceof GroupUse) {
                $this->collectUseStatement($statement, $statement->prefix->toString() . '\\', $imports);
            }
        }
    }

    /**
     * @param Use_|GroupUse $statement
     * @param array<string, true> $imports
     */
    private function collectUseStatement(Use_|GroupUse $statement, string $prefix, array &$imports): void
    {
        foreach ($statement->uses as $use) {
            $type = $use->type === Use_::TYPE_UNKNOWN ? $statement->type : $use->type;
            if ($type !== Use_::TYPE_NORMAL && $type !== Use_::TYPE_FUNCTION) {
                continue;
            }

            $name = $prefix . $use->name->toString();
            if (!str_starts_with($name, self::CONTRACT_NAMESPACE_PREFIX)) {
                continue;
            }

            $imports[$name] = true;
        }
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
