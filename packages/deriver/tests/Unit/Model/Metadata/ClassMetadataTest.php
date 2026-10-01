<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Metadata;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class ClassMetadataTest extends TestCase
{
    public function testMetadataRetainsMissingParentAndUnknownConstants(): void
    {
        $class = new \Deriver\Model\Metadata\ClassMetadata('Child', 'ExternalParent', constants: ['TABLE' => \Deriver\Value\Term::opaque('UNEVALUATED_INITIALIZER')]);
        self::assertSame('ExternalParent', $class->parent);
        self::assertFalse($class->constants['TABLE']->isConcrete());
    }
}
