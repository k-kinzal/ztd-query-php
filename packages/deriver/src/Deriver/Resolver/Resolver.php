<?php

declare(strict_types=1);

namespace Deriver\Deriver\Resolver;

use Deriver\Deriver\Parser\Node\File;
use Deriver\Exception\ResolutionException;
use LogicException;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitor\NameResolver;

/**
 * Resolves names and lexical context on a copy of a parsed syntax tree.
 * @visibility root
 */
final class Resolver
{
    /**
     * Prepares declarations and expressions for indexing without changing the parsed input.
     * @throws ResolutionException If names, scope, or expression constraints are invalid
     * @throws LogicException If traversal violates the file node contract
     */
    public function resolve(File $file): File
    {
        $errors = new Collecting();
        $copy = (new NodeTraverser(new CloningVisitor()))->traverse([$file]);
        $traverser = new NodeTraverser(new ClassScope($errors), new AssignmentPatterns($errors), new ExpressionValidation($errors), new NameResolver($errors), new MagicContext());
        $resolved = $traverser->traverse($copy)[0];
        $error = $errors->getErrors()[0] ?? null;
        if ($error !== null) {
            throw new ResolutionException($error->getRawMessage(), $file->source->path, max(1, $error->getStartLine()), $error);
        }
        if (!$resolved instanceof File) {
            throw new LogicException('Name resolution must preserve the file root.');
        }
        return $resolved;
    }
}
