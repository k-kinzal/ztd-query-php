<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public

 */
interface Window
{
    /**
     * @return list<\SqlSemantics\Model\Expression>
     */
    public function expressions(): array;
}
