<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public

 */
final class Frame
{
    /**
     */
    public function __construct(
        public readonly FrameUnit $unit,
        public readonly Boundary $start,
        public readonly Boundary $end,
        public readonly FrameExclusion $exclusion
    ) {
    }
}
