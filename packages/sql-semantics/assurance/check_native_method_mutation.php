<?php

declare(strict_types=1);

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

require $argv[1];
require __DIR__ . '/native_boundaries.php';

function requireShape(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    [, , $source, $dialect, $boundary] = $argv;
    $platform = 'SqlSemantics\\Platform\\' . match ($dialect) {
        'mysql' => 'MySql',
        'postgres' => 'PostgreSql',
        'sqlite' => 'Sqlite',
    };
    $rules = nativeBoundaryContract($dialect);
    requireShape(isset($rules[$boundary]), 'Native method target is not a registered boundary');
    $role = explode('::', $boundary, 2)[0];
    $expected = $platform . '\\Ir\\' . str_replace('/', '\\', $role);

    requireShape(is_file($source), 'Native method overlay source is absent');
    $code = file_get_contents($source);
    requireShape(is_string($code), 'Native method overlay source is unreadable');
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $traverser = new NodeTraverser(new NameResolver());
    $tree = $traverser->traverse($parser->parse($code));
    $classes = (new NodeFinder())->findInstanceOf($tree, ClassLike::class);
    requireShape(count($classes) === 1 && $classes[0]->namespacedName->toString() === $expected,
        'Native method overlay declares the wrong type');

    $checked = checkNativeBoundaries($classes[0], $role, $platform, $dialect, $rules, $parser, $traverser, new Standard());
    requireShape(in_array($boundary, $checked, true), 'Native method overlay did not check its registered boundary');
    echo json_encode(['status' => 'method-target-verified', 'boundary' => $boundary], JSON_THROW_ON_ERROR) . "\n";
} catch (RuntimeException $failure) {
    echo json_encode(['status' => 'shape-mismatch', 'message' => $failure->getMessage()], JSON_THROW_ON_ERROR) . "\n";
    exit(1);
}
