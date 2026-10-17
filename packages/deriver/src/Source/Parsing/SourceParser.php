<?php

declare(strict_types=1);

namespace Deriver\Source\Parsing;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

/**
 * Parses captured PHP bytes for one target profile into a resolved syntax tree with its diagnostics.
 * This is the parsing stage: it admits the input against the source limits, runs the upstream parser
 * and name resolver, rejects grammar the target does not have, and never executes or lowers anything.
 * @visibility namespace
 * @visibility Deriver\Session
 */
final class SourceParser
{
    /**
     * Runs the loaded parser and name resolver, capturing all errors as source diagnostics.
     * @param string $contents PHP source bytes
     * @param TargetProfile $profile Grammar profile
     * @param SourceLimits $limits Source admission policy
     * @return ParsedFile Resolved parse result
     * @throws InvalidInputException If parser output violates its top-level contract
     */
    public function parse(string $contents, TargetProfile $profile, SourceLimits $limits = new SourceLimits()): ParsedFile
    {
        if (strlen($contents) > $limits->fileBytes) {
            throw new InvalidInputException('SOURCE_LIMIT: parser input exceeds the admission limit.');
        }
        $errors = new Collecting();
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromString($profile->version));
        $parsed = $parser->parse($contents, $errors) ?? [];
        [$size, $depth] = (new SyntaxSize())->measure(array_values($parsed), $limits);
        $nodes = [];
        if (!$errors->hasErrors()) {
            foreach ((new NodeTraverser(new TargetSyntax($profile, $errors, array_values($parser->getTokens())), new ClassScope($errors), new AssignmentPatterns($errors), new NameResolver($errors), new MagicContext()))->traverse($parsed) as $node) {
                if (!$node instanceof Stmt) {
                    throw new InvalidInputException('The parser returned an invalid top-level node.');
                }
                $nodes[] = $node;
            }
        }
        $diagnostics = [];
        foreach ($errors->getErrors() as $error) {
            $diagnostics[] = ['message' => $error->getRawMessage(), 'line' => max(1, $error->getStartLine())];
        }
        return new ParsedFile($errors->hasErrors() ? [] : $nodes, $diagnostics, $size, $depth);
    }
}
