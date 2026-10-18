Feature: PHP forms value derivation
  Each scenario captures source and derives a result without executing the fixture.

  @syntax @SPEC-SYNTAX-NULL-LITERAL
  Scenario: Null Literal
    Given PHP "8.1" source "Syntax/NullLiteral.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | null |

  @syntax @SPEC-SYNTAX-BOOLEAN-LITERAL
  Scenario: Boolean Literal
    Given PHP "8.1" source "Syntax/BooleanLiteral.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-FALSE-LITERAL
  Scenario: False Literal
    Given PHP "8.1" source "Syntax/FalseLiteral.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | false |

  @syntax @SPEC-SYNTAX-DECIMAL-INTEGER
  Scenario: Decimal Integer
    Given PHP "8.1" source "Syntax/DecimalInteger.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 42 |

  @syntax @SPEC-SYNTAX-HEXADECIMAL-INTEGER
  Scenario: Hexadecimal Integer
    Given PHP "8.1" source "Syntax/HexadecimalInteger.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 42 |

  @syntax @SPEC-SYNTAX-BINARY-INTEGER
  Scenario: Binary Integer
    Given PHP "8.1" source "Syntax/BinaryInteger.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 42 |

  @syntax @SPEC-SYNTAX-OCTAL-INTEGER
  Scenario: Octal Integer
    Given PHP "8.1" source "Syntax/OctalInteger.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 42 |

  @syntax @SPEC-SYNTAX-EXPLICIT-OCTAL-INTEGER
  Scenario: Explicit Octal Integer
    Given PHP "8.1" source "Syntax/ExplicitOctalInteger.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 42 |

  @syntax @SPEC-SYNTAX-SEPARATED-INTEGER
  Scenario: Separated Integer
    Given PHP "8.1" source "Syntax/SeparatedInteger.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 42 |

  @syntax @SPEC-SYNTAX-DECIMAL-FLOAT
  Scenario: Decimal Float
    Given PHP "8.1" source "Syntax/DecimalFloat.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 1.25 |

  @syntax @SPEC-SYNTAX-EXPONENT-FLOAT
  Scenario: Exponent Float
    Given PHP "8.1" source "Syntax/ExponentFloat.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 1.25 |

  @syntax @SPEC-SYNTAX-SINGLE-QUOTED-STRING
  Scenario: Single Quoted String
    Given PHP "8.1" source "Syntax/SingleQuotedString.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "hello" |

  @syntax @SPEC-SYNTAX-DOUBLE-QUOTED-STRING
  Scenario: Double Quoted String
    Given PHP "8.1" source "Syntax/DoubleQuotedString.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "hello\\nworld" |

  @syntax @SPEC-SYNTAX-INTERPOLATED-STRING
  Scenario: Interpolated String
    Given PHP "8.1" source "Syntax/InterpolatedString.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "hello world" |

  @syntax @SPEC-SYNTAX-STRING-OFFSET
  Scenario: String Offset
    Given PHP "8.1" source "Syntax/StringOffset.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "b" |

  @syntax @SPEC-SYNTAX-HEREDOC
  Scenario: Heredoc
    Given PHP "8.1" source "Syntax/Heredoc.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "hello world" |

  @syntax @SPEC-SYNTAX-NOWDOC
  Scenario: Nowdoc
    Given PHP "8.1" source "Syntax/Nowdoc.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "hello $name" |

  @syntax @SPEC-SYNTAX-NUMERIC-STRING
  Scenario: Numeric String
    Given PHP "8.1" source "Syntax/NumericString.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 15 |

  @syntax @SPEC-SYNTAX-SHORT-ARRAY
  Scenario: Short Array
    Given PHP "8.1" source "Syntax/ShortArray.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [1,2] |

  @syntax @SPEC-SYNTAX-ARRAY-APPEND
  Scenario: Array Append
    Given PHP "8.1" source "Syntax/ArrayAppend.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [1,2] |

  @syntax @SPEC-SYNTAX-ARRAY-DESTRUCTURING
  Scenario: Array Destructuring
    Given PHP "8.1" source "Syntax/ArrayDestructuring.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 4 |

  @syntax @SPEC-SYNTAX-ARRAY-UNPACKING
  Scenario: Array Unpacking
    Given PHP "8.1" source "Syntax/ArrayUnpacking.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [1,2,3] |

  @syntax @SPEC-SYNTAX-LOCAL-VARIABLE
  Scenario: Local Variable
    Given PHP "8.1" source "Syntax/LocalVariable.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-VARIABLE-VARIABLE
  Scenario: Variable Variable
    Given PHP "8.1" source "Syntax/VariableVariable.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-VARIABLE-REFERENCE
  Scenario: Variable Reference
    Given PHP "8.1" source "Syntax/VariableReference.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-SUPERGLOBAL
  Scenario: Superglobal
    Given PHP "8.1" source "Syntax/Superglobal.php"
    And environment "$_GET" equals '{"id":7}'
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-NO-RETURN-VALUE
  Scenario: No Return Value
    Given PHP "8.1" source "Syntax/NoReturnValue.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | null |

  @syntax @SPEC-SYNTAX-DEFAULT-ARGUMENT
  Scenario: Default Argument
    Given PHP "8.1" source "Syntax/DefaultArgument.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-NAMED-ARGUMENT
  Scenario: Named Argument
    Given PHP "8.1" source "Syntax/NamedArgument.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-VARIADIC-ARGUMENT
  Scenario: Variadic Argument
    Given PHP "8.1" source "Syntax/VariadicArgument.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [3,7] |

  @syntax @SPEC-SYNTAX-ARGUMENT-UNPACKING
  Scenario: Argument Unpacking
    Given PHP "8.1" source "Syntax/ArgumentUnpacking.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-REFERENCE-ARGUMENT
  Scenario: Reference Argument
    Given PHP "8.1" source "Syntax/ReferenceArgument.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-REFERENCE-RETURN
  Scenario: Reference Return
    Given PHP "8.1" source "Syntax/ReferenceReturn.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-VARIABLE-FUNCTION
  Scenario: Variable Function
    Given PHP "8.1" source "Syntax/VariableFunction.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-CLOSURE
  Scenario: Closure
    Given PHP "8.1" source "Syntax/Closure.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-CLOSURE-REFERENCE-CAPTURE
  Scenario: Closure Reference Capture
    Given PHP "8.1" source "Syntax/ClosureReferenceCapture.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-FIRST-CLASS-CALLABLE
  Scenario: First Class Callable
    Given PHP "8.1" source "Syntax/FirstClassCallable.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-NULLABLE-TYPE
  Scenario: Nullable Type
    Given PHP "8.1" source "Syntax/NullableType.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | null |

  @syntax @SPEC-SYNTAX-UNION-TYPE
  Scenario: Union Type
    Given PHP "8.1" source "Syntax/UnionType.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "seven" |

  @syntax @SPEC-SYNTAX-INTERSECTION-TYPE
  Scenario: Intersection Type
    Given PHP "8.1" source "Syntax/IntersectionType.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-DNF-TYPE
  Scenario: Dnf Type
    Given PHP "8.2" source "Syntax/DnfType.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-MIXED-TYPE
  Scenario: Mixed Type
    Given PHP "8.1" source "Syntax/MixedType.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-ITERABLE-TYPE
  Scenario: Iterable Type
    Given PHP "8.1" source "Syntax/IterableType.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |
      | null |

  @syntax @SPEC-SYNTAX-NEVER-TYPE
  Scenario: Never Type
    Given PHP "8.1" source "Syntax/NeverType.php"
    When I derive "subject#return"
    Then the candidate has no return value

  @syntax @SPEC-SYNTAX-STATIC-METHOD
  Scenario: Static Method
    Given PHP "8.1" source "Syntax/StaticMethod.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-CLASS-CONSTANT
  Scenario: Class Constant
    Given PHP "8.1" source "Syntax/ClassConstant.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-LATE-STATIC-BINDING
  Scenario: Late Static Binding
    Given PHP "8.1" source "Syntax/LateStaticBinding.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-SELF-TYPE
  Scenario: Self Type
    Given PHP "8.1" source "Syntax/SelfType.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-MAGIC-STRING
  Scenario: Magic String
    Given PHP "8.1" source "Syntax/MagicString.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "seven" |

  @syntax @SPEC-SYNTAX-ENUM-BACKING
  Scenario: Enum Backing
    Given PHP "8.1" source "Syntax/EnumBacking.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "ready" |

  @syntax @SPEC-SYNTAX-ENUM-NAME
  Scenario: Enum Name
    Given PHP "8.1" source "Syntax/EnumName.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "Ready" |

  @syntax @SPEC-SYNTAX-ENUM-METHOD
  Scenario: Enum Method
    Given PHP "8.1" source "Syntax/EnumMethod.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "ready" |

  @syntax @SPEC-SYNTAX-NAMESPACE-ALIAS
  Scenario: Namespace Alias
    Given PHP "8.1" source "Syntax/NamespaceAlias.php"
    When I derive "Demo\subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-GROUP-IMPORT
  Scenario: Group Import
    Given PHP "8.1" source "Syntax/GroupImport.php"
    When I derive "Demo\subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-ATTRIBUTE-DECLARATION
  Scenario: Attribute Declaration
    Given PHP "8.1" source "Syntax/AttributeDeclaration.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-ANONYMOUS-CLASS
  Scenario: Anonymous Class
    Given PHP "8.1" source "Syntax/AnonymousClass.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-READONLY-CLASS
  Scenario: Readonly Class
    Given PHP "8.2" source "Syntax/ReadonlyClass.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-PROPERTY-HOOK
  Scenario: Property Hook
    Given PHP "8.4" source "Syntax/PropertyHook.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-ASYMMETRIC-VISIBILITY
  Scenario: Asymmetric Visibility
    Given PHP "8.4" source "Syntax/AsymmetricVisibility.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-UNPARENTHESIZED-NEW
  Scenario: Unparenthesized New
    Given PHP "8.4" source "Syntax/UnparenthesizedNew.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-PHP-TAGS
  Scenario: Php Tags
    Given PHP "8.1" source "Syntax/PhpTags.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-INLINE-HTML
  Scenario: Inline Html
    Given PHP "8.1" source "Syntax/InlineHtml.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-COMMENTS
  Scenario: Comments
    Given PHP "8.1" source "Syntax/Comments.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-INSTRUCTION-SEPARATION
  Scenario: Instruction Separation
    Given PHP "8.1" source "Syntax/InstructionSeparation.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-ARRAY-UNION
  Scenario: Array Union
    Given PHP "8.1" source "Syntax/ArrayUnion.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | {"a":1,"b":3} |

  @syntax @SPEC-SYNTAX-NULLSAFE-PROPERTY
  Scenario: Nullsafe Property
    Given PHP "8.1" source "Syntax/NullsafeProperty.php"
    When I derive "subject#return"
    Then the candidates retain:
      | value | kind | reason |
      | | reference | UNRESOLVED_PROPERTY |
      | null | | |

  @syntax @SPEC-SYNTAX-NULLSAFE-METHOD
  Scenario: Nullsafe Method
    Given PHP "8.1" source "Syntax/NullsafeMethod.php"
    When I derive "subject#return"
    Then the candidates retain:
      | value | kind | reason |
      | | no-value | NO_VALUE_DEFINITION |
      | null | | |

  @syntax @SPEC-SYNTAX-PROPERTY-SET-HOOK
  Scenario: Property Set Hook
    Given PHP "8.4" source "Syntax/PropertySetHook.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 14 |

  @syntax @SPEC-SYNTAX-BACKED-PROPERTY-GET-HOOK
  Scenario: Backed Property Get Hook
    Given PHP "8.4" source "Syntax/BackedPropertyGetHook.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 14 |

  @syntax @SPEC-SYNTAX-FIRST-CLASS-METHOD
  Scenario: First Class Method
    Given PHP "8.1" source "Syntax/FirstClassMethod.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-FIRST-CLASS-STATIC-METHOD
  Scenario: First Class Static Method
    Given PHP "8.1" source "Syntax/FirstClassStaticMethod.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-ANONYMOUS-CLASS-METHOD
  Scenario: Anonymous Class Method
    Given PHP "8.1" source "Syntax/AnonymousClassMethod.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-REFERENCE-RETURN-READ
  Scenario: Reference Return Read
    Given PHP "8.1" source "Syntax/ReferenceReturnRead.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-UNSET-ORIGINAL-REFERENCE
  Scenario: Unset Original Reference
    Given PHP "8.1" source "Syntax/UnsetOriginalReference.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-CLOSURE-REFERENCE-READ
  Scenario: Closure Reference Read
    Given PHP "8.1" source "Syntax/ClosureReferenceRead.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-STATIC-VARIABLE-REPEATED
  Scenario: Static Variable Repeated
    Given PHP "8.1" source "Syntax/StaticVariableRepeated.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 8 |

  @syntax @SPEC-SYNTAX-FALSE-TYPE
  Scenario: False Type
    Given PHP "8.2" source "Syntax/FalseType.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | false |

  @syntax @SPEC-SYNTAX-TRUE-TYPE
  Scenario: True Type
    Given PHP "8.2" source "Syntax/TrueType.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-VOID-TYPE
  Scenario: Void Type
    Given PHP "8.1" source "Syntax/VoidType.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | null |

  @syntax @SPEC-SYNTAX-ENUM-STATIC-METHOD
  Scenario: Enum Static Method
    Given PHP "8.1" source "Syntax/EnumStaticMethod.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "ready" |

  @syntax @SPEC-SYNTAX-ENUM-CONSTANT
  Scenario: Enum Constant
    Given PHP "8.1" source "Syntax/EnumConstant.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "ready" |

  @syntax @SPEC-SYNTAX-OBJECT-REFERENCE
  Scenario: Object Reference
    Given PHP "8.1" source "Syntax/ObjectReference.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-NAMESPACE-RELATIVE-NAME
  Scenario: Namespace Relative Name
    Given PHP "8.1" source "Syntax/NamespaceRelativeName.php"
    When I derive "Demo\subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-NAMESPACE-FALLBACK
  Scenario: Namespace Fallback
    Given PHP "8.1" source "Syntax/NamespaceFallback.php"
    When I derive "Demo\subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-NESTED-NAMESPACE
  Scenario: Nested Namespace
    Given PHP "8.1" source "Syntax/NestedNamespace.php"
    When I derive "Outer\Inner\subject#return"
    Then the candidates are:
      | value |
      | "Outer\\\\Inner" |

  @syntax @SPEC-SYNTAX-DYNAMIC-NAMESPACED-FUNCTION
  Scenario: Dynamic Namespaced Function
    Given PHP "8.1" source "Syntax/DynamicNamespacedFunction.php"
    When I derive "Demo\subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-GLOBAL-NAMESPACE
  Scenario: Global Namespace
    Given PHP "8.1" source "Syntax/GlobalNamespace.php"
    When I derive "Demo\subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-ENUM-TRAIT
  Scenario: Enum Trait
    Given PHP "8.1" source "Syntax/EnumTrait.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "ready" |

  @syntax @SPEC-SYNTAX-OBJECT-IDENTITY
  Scenario: Object Identity
    Given PHP "8.1" source "Syntax/ObjectIdentity.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-OBJECT-DISTINCT-IDENTITY
  Scenario: Object Distinct Identity
    Given PHP "8.1" source "Syntax/ObjectDistinctIdentity.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | false |

  @syntax @SPEC-SYNTAX-COVARIANT-RETURN
  Scenario: Covariant Return
    Given PHP "8.1" source "Syntax/CovariantReturn.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |
