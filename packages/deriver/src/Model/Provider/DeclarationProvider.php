<?php

declare(strict_types=1);

namespace Deriver\Model\Provider;

/**
 * Provides captured declarations as data; SourceFile::declarationsOnly keeps signatures separate from bodies.
 * @visibility public
 * @example Providers are installed explicitly
 *     (new \Deriver\Api\Project\Configuration())->providers // => []
 */
interface DeclarationProvider extends Provider
{
    /**
     * @return \Deriver\Api\Project\ProjectInput Captured source or signature-only provider contribution
     * @throws \Deriver\Model\ModelException If trusted plugin code fails
     */
    public function declarations(): \Deriver\Api\Project\ProjectInput;
}
