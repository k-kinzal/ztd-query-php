<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Argument;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

#[CoversClass(Argument::class)]
#[Small]
final class ArgumentTest extends TestCase
{
    public function testValueIsTheExpressionPassed(): void
    {
        $value = new Constant(new IntegerConstant('1'));
        $argument = new class ($value) implements Argument {
            public function __construct(private readonly Scalar $value)
            {
            }

            public function value(): Scalar
            {
                return $this->value;
            }

            public function name(): ?Name
            {
                return null;
            }

            public function deriveClause(Derivation $derivation, Environment $environment): void
            {
            }

            public function render(Output $out): void
            {
            }
        };
        self::assertSame($value, $argument->value());
    }

    public function testNameIsTheParameterNamedOrNull(): void
    {
        $argument = new class () implements Argument {
            public function value(): Scalar
            {
                return new Constant(new IntegerConstant('1'));
            }

            public function name(): Name
            {
                return new Name('p');
            }

            public function deriveClause(Derivation $derivation, Environment $environment): void
            {
            }

            public function render(Output $out): void
            {
            }
        };
        self::assertSame('p', $argument->name()->value);
    }
}
