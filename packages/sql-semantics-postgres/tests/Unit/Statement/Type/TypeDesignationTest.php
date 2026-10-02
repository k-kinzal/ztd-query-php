<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ColumnDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;

#[CoversClass(TypeDesignation::class)]
#[Small]
final class TypeDesignationTest extends TestCase
{
    public function testEverySpellingOfATypeIsADesignation(): void
    {
        self::assertContains(TypeDesignation::class, class_implements(KeywordDesignation::class));
        self::assertContains(TypeDesignation::class, class_implements(NamedDesignation::class));
        self::assertContains(TypeDesignation::class, class_implements(ColumnDesignation::class));
    }
}
