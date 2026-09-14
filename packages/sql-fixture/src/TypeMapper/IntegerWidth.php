<?php

declare(strict_types=1);

namespace SqlFixture\TypeMapper;

/**
 * Integer storage widths represented by the supported SQL dialects.
 *
 * @visibility root
 */
enum IntegerWidth
{
    case Bits8;
    case Bits16;
    case Bits24;
    case Bits32;
    case Bits64;
}
