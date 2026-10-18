<?php

declare(strict_types=1);

namespace Deriver\Deriver\Parser\Node;

use Deriver\Deriver\Sources\Source;
use Override;
use PhpParser\Node\Stmt;
use PhpParser\NodeAbstract;

/**
 * A file's root node, sharing the Node interface and traversal protocol of its statements.
 * @visibility root
 * @example Inspecting an empty file node
 *     $file = new \Deriver\Deriver\Parser\Node\File(new \Deriver\Deriver\Sources\Source('empty.php', '<?php'), []);
 *     $file->getType() // => 'File'
 *     $file->getSubNodeNames() // => ['stmts']
 */
final class File extends NodeAbstract
{
    /**
     * @param Source $source Captured source providing the file's location
     * @param list<Stmt> $stmts Top-level statements in source order
     */
    public function __construct(public readonly Source $source, public array $stmts)
    {
        $contents = $source->contents;
        parent::__construct([
            'startLine' => 1,
            'endLine' => 1 + substr_count($contents, "\n") + substr_count($contents, "\r") - substr_count($contents, "\r\n"),
            'startFilePos' => 0,
            'endFilePos' => strlen($contents) - 1,
        ]);
    }

    /**
     * Identifies the root as a file within the PHP syntax tree.
     * @return non-empty-string Node discriminator
     */
    #[Override]
    public function getType(): string
    {
        return 'File';
    }

    /**
     * Declares the child field traversed by node visitors.
     * @return list<string> Structural child fields
     */
    #[Override]
    public function getSubNodeNames(): array
    {
        return ['stmts'];
    }
}
