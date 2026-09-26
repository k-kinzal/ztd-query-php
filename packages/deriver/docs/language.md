# PHP language semantics

Deriver analyzes the PHP 8.3, 64-bit target profile independently of the PHP version running the library. Source parsing and semantic support are separate: accepting a file does not imply that every operation in it can be evaluated precisely. Inspect the result's frontiers and assessment, including exceptional outcomes.

The source index also checks newer AST forms accepted by the parser, including partial function application, pipe operators, void casts, property hooks, and asymmetric property visibility. Syntax outside the target version produces a project diagnostic before lowering.

The analyzer reads application files as data. It does not load application classes, run their constructors, or use their autoloaders. Object identities and property storage exist only in the analysis state.

## Methods and object construction

Known source methods use PHP visibility rules and the lexical calling class. A private parent method remains distinct from a child method with the same name. Protected access follows the captured inheritance hierarchy. A static method has no bound `$this`; using an instance method with static-call syntax requires a compatible existing receiver.

The analyzer routes missing or inaccessible methods through a captured `__call` or `__callStatic` implementation. Magic calls receive the original method name and an argument array whose named keys are preserved. Ordinary argument binding, exceptions, and state changes apply to these bodies.

Construction checks abstract classes, interfaces, enums, constructor access, and constructor arguments. Known runtime objects can supply the class for `new $object`. Cloning keeps child object identities shared while giving the outer object its own property storage, then applies the captured `__clone` body.

An unresolved class does not become a known empty object. Its allocation retains an explicit boundary, possible state changes, and both normal and exceptional alternatives.

These rules are checked against independent PHP 8.3 executions in [the method corpus](../tests/Semantic/MethodSemanticsTest.php). The PHP manual describes [visibility](https://www.php.net/manual/en/language.oop5.visibility.php), [static methods](https://www.php.net/manual/en/language.oop5.static.php), and [magic method dispatch](https://www.php.net/manual/en/language.oop5.overloading.php).

## Argument evaluation and callables

A call resolves its receiver and signature before evaluating arguments. Known access and construction errors therefore preserve the state before argument side effects. An unresolved external call also retains the possibility of an early lookup error; later invocation still carries its model or dispatch boundary.

Weak scalar coercion preserves exact union members regardless of declaration order. Numeric strings choose integer or float union members using the target numeric category. Integer limits are checked before converting digits through a float, and implicit integer conversions that lose precision record the target diagnostic. Shared reference arguments see conversions already completed for preceding parameters.

Each value argument is frozen when its expression is evaluated. Each reference argument retains its original cell, whose current value is checked when parameters are bound. This distinction matters when a later argument changes or rebinds the same variable. Missing variables and array elements passed by reference become null without an undefined-value read. Property types, readonly rules, string-offset restrictions, and `ArrayAccess` reference returns use the same checks as ordinary reference operations.

Known array unpacking preserves positional and named keys. Reference parameters update elements of plain array variables and arrays returned by reference. Unpacking a property, nested array element, or value-returning expression uses temporary array storage, while existing reference elements remain shared. Unresolved iterator unpacking records a language boundary with possible effects and exceptions.

First-class callable acquisition checks visibility immediately and preserves the acquiring scope, bound object, and late static binding. Private methods acquired within their class can be invoked outside it. `self` and `parent` preserve the called class; an explicit class name selects that class. Ordinary calls preserve their caller's class context. Static closures have no bound `$this`, and separate closure allocations have distinct identities.

The [argument corpus](../tests/Fake/Programs/ArgumentPrograms.php) and [callable corpus](../tests/Fake/Programs/CallablePrograms.php) check these rules independently against PHP 8.3. See the PHP manual for [arguments](https://www.php.net/manual/en/functions.arguments.php), [first-class callables](https://www.php.net/manual/en/functions.first_class_callable_syntax.php), and [late static binding](https://www.php.net/manual/en/language.oop5.late-static-bindings.php).

## Traits

Trait members are imported into each consuming class before callable bodies are lowered. Private property slots, `self`, `parent`, and class-bound closures therefore use the consuming class. The original trait name remains available through `__TRAIT__`, and method aliases preserve the original `__METHOD__` and `__FUNCTION__` values.

The implementation handles nested trait uses, `insteadof`, aliases, and visibility changes. A class's own methods take precedence over imported methods; imported methods take precedence over inherited methods. An unresolved concrete method conflict produces an invalid-program diagnostic.

Static storage follows the PHP 8.3 rules, including separate storage when both a parent and child explicitly use the same trait. Changing only the consuming class's adaptations invalidates its cached trait graphs. See the [PHP trait documentation](https://www.php.net/manual/en/language.oop5.traits.php) for the language rules and version differences.

## Native throwable objects

An explicit throw accepts only a `Throwable`. Invalid scalar, array, closure, enum, or ordinary object operands produce `Error`. For symbolic inputs, catch clauses partition the declared type bound in source order; a later catch cannot recover a subtype already consumed by an earlier catch. Uncaught subsets remain exceptional outcomes.

When a finally block throws while another exception is pending, the displaced exception is appended to the replacement's `previous` chain. Existing links and object identity are retained, and duplicate or cyclic links are avoided. A pending return or jump does not add a previous exception. The [exception corpus](../tests/Differential/ExceptionSemanticsTest.php) checks these rules against PHP 8.3, whose [exception implementation](https://github.com/php/php-src/blob/PHP-8.3/Zend/zend_exceptions.c) defines the chaining behavior.


Deriver has explicit target definitions for the standard `Exception` and `Error` hierarchies, including SPL exception subclasses and `ErrorException`. It applies constructor signatures through the same argument and type checks used for source calls. Source subclasses inherit these native constructors unless they declare their own.

The supported native getters are `getMessage()`, `getCode()`, `getPrevious()`, `getFile()`, `getLine()`, and `ErrorException::getSeverity()`. Native properties share the ordinary analysis heap, so a source subclass's property updates and calls to `parent::__construct()` remain visible. Throwable objects cannot be cloned.

A native constructor can deliberately retain an existing property. For example, passing a zero exception code preserves a nonzero code initialized by a subclass. A null previous exception preserves an existing previous link. The tests verify these behaviors against the [PHP 8.3 implementation](https://github.com/php/php-src/blob/PHP-8.3/Zend/zend_exceptions.c), as well as the documented [Exception](https://www.php.net/manual/en/exception.construct.php) and [ErrorException](https://www.php.net/manual/en/errorexception.construct.php) signatures.

Runtime stack metadata starts as an opaque value. An explicit `ErrorException` filename or line can make those fields concrete. Stack formatting, serialization hooks, and other native methods without a model retain a model boundary.

A caught runtime error has a stable object identity. Code can inspect its class, pass it to another function, compare aliases, use supported native getters, or rethrow it. Runtime-generated diagnostic messages remain opaque. An unknown throwable keeps an unknown runtime class after it is caught. The `get_class($object)` model reads a known runtime class without loading the application class; its omitted-argument form remains a model boundary.

## References and scalar updates

A reference to a typed property carries that property's type constraint. When several typed properties share a cell, writes must satisfy all their constraints with the same coerced result. This also applies when a property is first passed to a reference parameter or a variadic reference parameter.

Taking a reference to an uninitialized nullable property initializes it to null. An uninitialized non-nullable property cannot expose a reference. Invalid writes and increments preserve the state that existed before that failed update.

Increment and decrement use the target profile's scalar behavior. In particular, alphanumeric string increment is implemented independently of the host's `++` operator. Numeric strings, ASCII carry, pre/post results, and PHP 8.3 warnings are checked in [the increment corpus](../tests/Fake/Programs/IncrementPrograms.php). Target warnings appear as `PHP_WARNING` frontiers. The [PHP increment documentation](https://www.php.net/manual/en/language.operators.increment.php) describes the changes across PHP versions.

## Array and string offsets

Offset operations retain their original key until the container is known. This matters because arrays and strings accept different keys, and `isset()` has different string-key rules from a normal read. Invalid reads and writes preserve the target exception category and any state changes completed before the error.

Creating an array through a null or absent value is observable even if a later key is invalid. For example, `$a = null; $a[[]] = 1` leaves `$a` as an empty array before throwing `TypeError`. Intermediate arrays and typed-property constraints follow the same rule. Appending at the largest integer key fails while that key is occupied; removing it allows that index to be reused.

String offsets select bytes. Reads and writes support negative offsets; writes beyond the end add spaces. Assigning a longer string stores and returns its first byte and records the target warning. Reference binding, increment, compound assignment, and `unset()` on string offsets follow their own error rules. The analyzer checks its memory limit before allocating padding for a large offset.

Compound assignments read the destination after evaluating the right operand. Thus `$a = 1; $a += ($a = 2)` produces `4`. Missing array elements become null before applying the operator, so a later operator error preserves that initialized slot.

Known `ArrayAccess` implementations invoke captured `offsetGet`, `offsetSet`, `offsetExists`, and `offsetUnset` bodies. The original key reaches the method without array-key conversion. Existence tests, coalescing reads, nested access, and reference returns preserve method order and effects. Indirect updates to a value returned by `offsetGet` use temporary storage and record PHP's diagnostic; a reference return updates the shared cell. Unresolved offset protocols preserve possible state changes and both exit kinds behind an explicit frontier.

The [offset corpus](../tests/Fake/Programs/OffsetPrograms.php) records values, diagnostics, exceptions, and state observed after failures. Its differential suite independently executes each trusted fixture under PHP 8.3. Language references: [arrays](https://www.php.net/manual/en/language.types.array.php), [string offsets](https://www.php.net/manual/en/language.types.string.php), [ArrayAccess](https://www.php.net/manual/en/class.arrayaccess.php), and [PHP 8.3 offset execution](https://github.com/php/php-src/blob/PHP-8.3/Zend/zend_execute.c).

## Integer conversions

Integral operators and explicit float-to-integer casts use PHP 8.3 signed 64-bit wrapping. They do not invoke host casts on out-of-range floats. Implicit precision loss records a target diagnostic, while explicit casts follow their own warning rules. Shift counts are validated after integer conversion. The [integer corpus](../tests/Fake/Programs/IntegerPrograms.php) compares boundary values, large floats, offsets, and exceptional paths against PHP 8.3. The conversion rules are implemented from the [PHP 8.3 integer conversion routines](https://github.com/php/php-src/blob/PHP-8.3/Zend/zend_operators.h).

## Conversion and lifetime boundaries

Explicit string casts and concatenation invoke a captured `__toString()` body with its effects and exceptions. The implicit string return contract is checked even when the method omits a written return type. Arrays convert to `"Array"` with a target warning; objects without a string method, closures, and enums produce an error. The [conversion corpus](../tests/Fake/Programs/ConversionPrograms.php) checks these behaviors against PHP 8.3. See the [PHP magic method rules](https://www.php.net/manual/en/language.oop5.magic.php).

Weak argument, property, and return coercion involving `__toString()` currently produces an explicit `object-string-coercion` language frontier. It retains an opaque string result, reachable state effects, and an arbitrary throwable alternative. Object comparisons that require property traversal, non-Boolean/non-string object casts, and conversions of unconstrained values that may invoke object methods also retain explicit language boundaries.

Allocating a class with a source destructor records `destructor-lifetime` and seals the current computation with residual state and completion alternatives. Exact destructor timing depends on reference lifetimes and cycle collection; this profile does not claim to model it. The [PHP destructor documentation](https://www.php.net/manual/en/language.oop5.decon.php) describes these timing rules.

Symbolic division, remainder, and shifts retain guarded zero-divisor or negative-shift exceptions. Numeric operators also retain possible type failures when the operand types do not establish valid numeric conversion; this loss of precision is recorded as `WIDENED`. Supported integer guards can eliminate a contradicted error path.

Unknown calls can unset typed properties and modify static storage before its first read. The resulting state preserves possible uninitialized-property errors and does not restore a stale static default. A symbolic object parameter's declared property can likewise be uninitialized.

The target syntax check rejects unparenthesized dereferencing of `new` expressions introduced in PHP 8.4 and first-class callable constants/defaults introduced in PHP 8.5. Parenthesized PHP 8.3 expressions and ordinary first-class callable expressions remain accepted.
