<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Declaration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class ClassMetadataTest extends TestCase
{
    public function testMetadataRetainsMissingParentAndUnknownConstants(): void
    {
        $class = new \Deriver\Deriver\Evaluator\Declaration\ClassMetadata('Child', 'ExternalParent', constants: ['TABLE' => \Deriver\Deriver\Evaluator\Term::opaque('UNEVALUATED_INITIALIZER')]);
        self::assertSame('ExternalParent', $class->parent);
        self::assertFalse($class->constants['TABLE']->isConcrete());
    }
}
