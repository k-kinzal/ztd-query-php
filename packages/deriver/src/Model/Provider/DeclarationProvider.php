<?php

declare(strict_types=1);

namespace Deriver\Model\Provider;

use Deriver\Project\ProjectInput;

/**
 * Provides captured declarations as data; SourceFile::declarationsOnly keeps signatures separate from bodies.
 * @visibility public
 * @example Providers are installed explicitly
 *     (new \Deriver\Project\Configuration())->providers // => []
 */
interface DeclarationProvider extends Provider
{
    /**
     * @return ProjectInput Captured source or signature-only provider contribution
     * @throws \Deriver\Exception\ModelException If trusted plugin code fails
     */
    public function declarations(): ProjectInput;
}
