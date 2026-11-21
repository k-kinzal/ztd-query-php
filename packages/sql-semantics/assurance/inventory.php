<?php

declare(strict_types=1);

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

// The input may be a Git archive of the baseline. Candidate PHP is read as data.
if ($argc !== 3) {
    fwrite(STDERR, "Usage: php inventory.php <source-root> <tool-autoload.php>\n");
    exit(2);
}

require $argv[2];

$root = rtrim($argv[1], '/');
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$finder = new NodeFinder();
$printer = new Standard();
$traverser = new NodeTraverser(new NameResolver());
$packages = [];
foreach (['sql-semantics', 'sql-semantics-mysql', 'sql-semantics-postgres', 'sql-semantics-sqlite'] as $package) {
    $packageRoot = $root . '/packages/' . $package;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packageRoot . '/src'));
    $classes = [];
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $code = file_get_contents($file->getPathname());
        $nodes = $traverser->traverse($parser->parse($code));
        foreach ($finder->findInstanceOf($nodes, ClassLike::class) as $type) {
            if ($type->name === null) {
                continue;
            }
            $name = $type->namespacedName->toString();
            $properties = [];
            foreach ($type->getProperties() as $property) {
                foreach ($property->props as $member) {
                    $properties['$' . $member->name->toString()] = $printer->prettyPrint([$property]);
                }
            }
            $methods = [];
            foreach ($type->getMethods() as $method) {
                if ($method->isPublic() || str_starts_with($method->name->toString(), '__')) {
                    $signature = clone $method;
                    $signature->stmts = null;
                    $signature->setAttribute('comments', []);
                    $methods[] = $printer->prettyPrint([$signature]);
                }
            }
            $classes[$name] = [
                'file' => substr($file->getPathname(), strlen($root) + 1),
                'sha256' => hash('sha256', $code),
                'kind' => $type->getType(),
                'abstract' => $type instanceof Class_ && $type->isAbstract(),
                'visibility' => preg_match('/@visibility\s+([^\s*]+)/', $type->getDocComment()?->getText() ?? '', $match) === 1 ? $match[1] : null,
                'roles' => isset($type->implements) ? array_map(static fn ($role) => $role->toString(), $type->implements) : [],
                'properties' => $properties,
                'traits' => array_map($printer->prettyPrint(...), array_map(
                    static fn ($use) => [$use],
                    $finder->findInstanceOf($type->stmts, \PhpParser\Node\Stmt\TraitUse::class),
                )),
                'public_and_magic_signatures' => $methods,
            ];
        }
    }
    ksort($classes);
    $packages[$package] = [
        'grammar_artifacts' => array_map('basename', glob($packageRoot . '/resources/productions/*.php')),
        'classes' => $classes,
    ];
}

echo json_encode($packages, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
