<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

#[CoversClass(OptionArgument::class)]
#[Small]
final class OptionArgumentTest extends TestCase
{
    public function testTheValueKindsOfTheOptionSyntaxAreArguments(): void
    {
        self::assertContains(OptionArgument::class, class_implements(TypeName::class));
        self::assertContains(OptionArgument::class, class_implements(SignedNumber::class));
        self::assertContains(OptionArgument::class, class_implements(StringConstant::class));
        self::assertContains(OptionArgument::class, class_implements(OperatorName::class));
        self::assertContains(OptionArgument::class, class_implements(Toggle::class));
    }
}
