# ztd-query-phpstan-custom-rules

Project-specific PHPStan rules for ztd-query packages.

## Rules

- `customRules.phpstanIgnoreComment`
  - Prohibits `@phpstan-ignore*` comments.
- `customRules.infectionIgnoreAllComment`
  - Prohibits `@infection-ignore-all` comments.
- `customRules.doubleSlashComment`
  - Prohibits line comments that start with `//`.
- `customRules.testClassProperty`
  - Prohibits properties in classes under `Tests\Unit\*` and `Tests\Integration\*`.
- `customRules.testClassConstant`
  - Prohibits class constants in classes under `Tests\Unit\*` and `Tests\Integration\*`.
- `customRules.testClassPrivateMethod`
  - Prohibits private methods in classes under `Tests\Unit\*` and `Tests\Integration\*`.
- `customRules.testClassPhpUnitMockProhibitedApi`
  - Prohibits PHPUnit mock APIs that bypass interface-first test doubles: `getMockBuilder`, `createPartialMock`, `createTestProxy`, `getMockForAbstractClass`, `getMockForTrait`, `getMockFromWsdl`.
- `customRules.testClassPhpUnitMockRequiresInterface`
  - Requires `createMock`, `createConfiguredMock`, `createStub`, and `createConfiguredStub` to target interfaces only in classes under `Tests\*`.
- `customRules.testClassPhpUnitMockRequiresLiteralInterface`
  - Requires the target type passed to interface-only PHPUnit mock APIs to be a direct class-string literal (for example, `DependencyInterface::class`) in classes under `Tests\*`.
- `customRules.testClassPhpUnitMockProhibitedInstantiation`
  - Prohibits direct instantiation of `PHPUnit\Framework\MockObject\MockBuilder` and `PHPUnit\Framework\MockObject\Generator\Generator` in classes under `Tests\*`.
- `customRules.srcWithoutUnitTest`
  - Requires a matching file at `tests/Unit/**/*Test.php` for every `src/**/*.php`.
- `customRules.unitTestWithoutSource`
  - Requires a matching file at `src/**/*.php` for every `tests/Unit/**/*.php`.

## Usage

In each package `phpstan.neon`:

```neon
includes:
    - vendor/k-kinzal/phpstan-custom-rules/extension.neon
```
