Feature: PHP keywords value derivation
  Each scenario captures source and derives a result without executing the fixture.

  @syntax @SPEC-SYNTAX-LONG-ARRAY
  Scenario: Long Array
    Given PHP "8.1" source "Syntax/LongArray.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | {"key":7} |

  @syntax @SPEC-SYNTAX-LIST-DESTRUCTURING
  Scenario: List Destructuring
    Given PHP "8.1" source "Syntax/ListDestructuring.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 3 |

  @syntax @SPEC-SYNTAX-UNSET-REFERENCE
  Scenario: Unset Reference
    Given PHP "8.1" source "Syntax/UnsetReference.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-UNSET-ARRAY-ELEMENT
  Scenario: Unset Array Element
    Given PHP "8.1" source "Syntax/UnsetArrayElement.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | {"keep":7} |

  @syntax @SPEC-SYNTAX-GLOBAL-VARIABLE
  Scenario: Global Variable
    Given PHP "8.1" source "Syntax/GlobalVariable.php"
    And environment "$value" equals '7'
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-STATIC-VARIABLE
  Scenario: Static Variable
    Given PHP "8.1" source "Syntax/StaticVariable.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-GLOBAL-CONSTANT
  Scenario: Global Constant
    Given PHP "8.1" source "Syntax/GlobalConstant.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-FUNCTION-DEFINITION
  Scenario: Function Definition
    Given PHP "8.1" source "Syntax/FunctionDefinition.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-CLOSURE-CAPTURE
  Scenario: Closure Capture
    Given PHP "8.1" source "Syntax/ClosureCapture.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-CALLABLE-PARAMETER
  Scenario: Callable Parameter
    Given PHP "8.1" source "Syntax/CallableParameter.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-STRICT-TYPES
  Scenario: Strict Types
    Given PHP "8.1" source "Syntax/StrictTypes.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-IF-ELSE
  Scenario: If Else
    Given PHP "8.1" source "Syntax/IfElse.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |
      | 9 |

  @syntax @SPEC-SYNTAX-ELSE-IF
  Scenario: Else If
    Given PHP "8.1" source "Syntax/ElseIf.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 1 |
      | 7 |
      | 9 |

  @syntax @SPEC-SYNTAX-ALTERNATIVE-IF
  Scenario: Alternative If
    Given PHP "8.1" source "Syntax/AlternativeIf.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 1 |
      | 7 |

  @syntax @SPEC-SYNTAX-WHILE-LOOP
  Scenario: While Loop
    Given PHP "8.1" source "Syntax/WhileLoop.php"
    When I derive "subject#return"
    Then the candidates retain:
      | value | kind | reason |
      | | recurrence | LOOP_DEPENDENCY |

  @syntax @SPEC-SYNTAX-DO-WHILE-LOOP
  Scenario: Do While Loop
    Given PHP "8.1" source "Syntax/DoWhileLoop.php"
    When I derive "subject#return"
    Then the candidates retain:
      | value | kind | reason |
      | | recurrence | LOOP_DEPENDENCY |

  @syntax @SPEC-SYNTAX-FOR-LOOP
  Scenario: For Loop
    Given PHP "8.1" source "Syntax/ForLoop.php"
    When I derive "subject#return"
    Then the candidates retain:
      | value | kind | reason |
      | | recurrence | LOOP_DEPENDENCY |

  @syntax @SPEC-SYNTAX-FOREACH-LOOP
  Scenario: Foreach Loop
    Given PHP "8.1" source "Syntax/ForeachLoop.php"
    When I derive "subject#return"
    Then the candidates retain:
      | value | kind | reason |
      | | recurrence | LOOP_DEPENDENCY |

  @syntax @SPEC-SYNTAX-FOREACH-KEY
  Scenario: Foreach Key
    Given PHP "8.1" source "Syntax/ForeachKey.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "key" |
      | null |

  @syntax @SPEC-SYNTAX-ALTERNATIVE-WHILE
  Scenario: Alternative While
    Given PHP "8.1" source "Syntax/AlternativeWhile.php"
    When I derive "subject#return"
    Then the candidates retain:
      | value | kind | reason |
      | | recurrence | LOOP_DEPENDENCY |

  @syntax @SPEC-SYNTAX-ALTERNATIVE-FOR
  Scenario: Alternative For
    Given PHP "8.1" source "Syntax/AlternativeFor.php"
    When I derive "subject#return"
    Then the candidates retain:
      | value | kind | reason |
      | | recurrence | LOOP_DEPENDENCY |

  @syntax @SPEC-SYNTAX-ALTERNATIVE-FOREACH
  Scenario: Alternative Foreach
    Given PHP "8.1" source "Syntax/AlternativeForeach.php"
    When I derive "subject#return"
    Then the candidates retain:
      | value | kind | reason |
      | | recurrence | LOOP_DEPENDENCY |

  @syntax @SPEC-SYNTAX-BREAK-LOOP
  Scenario: Break Loop
    Given PHP "8.1" source "Syntax/BreakLoop.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 0 |
      | 7 |

  @syntax @SPEC-SYNTAX-CONTINUE-LOOP
  Scenario: Continue Loop
    Given PHP "8.1" source "Syntax/ContinueLoop.php"
    When I derive "subject#return"
    Then the candidates retain:
      | value | kind | reason |
      | | recurrence | LOOP_DEPENDENCY |

  @syntax @SPEC-SYNTAX-SWITCH
  Scenario: Switch
    Given PHP "8.1" source "Syntax/Switch.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 1 |
      | 7 |
      | 9 |

  @syntax @SPEC-SYNTAX-SWITCH-DEFAULT
  Scenario: Switch Default
    Given PHP "8.1" source "Syntax/SwitchDefault.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 1 |
      | 7 |

  @syntax @SPEC-SYNTAX-ALTERNATIVE-SWITCH
  Scenario: Alternative Switch
    Given PHP "8.1" source "Syntax/AlternativeSwitch.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 1 |
      | 7 |

  @syntax @SPEC-SYNTAX-MATCH
  Scenario: Match
    Given PHP "8.1" source "Syntax/Match.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 3 |
      | 7 |
      | 9 |

  @syntax @SPEC-SYNTAX-GOTO
  Scenario: Goto
    Given PHP "8.1" source "Syntax/Goto.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-CATCH-EXCEPTION
  Scenario: Catch Exception
    Given PHP "8.1" source "Syntax/CatchException.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-FINALLY-RETURN
  Scenario: Finally Return
    Given PHP "8.1" source "Syntax/FinallyReturn.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-ECHO-ASSIGNMENT
  Scenario: Echo Assignment
    Given PHP "8.1" source "Syntax/EchoAssignment.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-ISSET-EXPRESSION
  Scenario: Isset Expression
    Given PHP "8.1" source "Syntax/IssetExpression.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |
      | false |

  @syntax @SPEC-SYNTAX-EMPTY-EXPRESSION
  Scenario: Empty Expression
    Given PHP "8.1" source "Syntax/EmptyExpression.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-END-DECLARE
  Scenario: End Declare
    Given PHP "8.1" source "Syntax/EndDeclare.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-HALT-COMPILER
  Scenario: Halt Compiler
    Given PHP "8.1" source "Syntax/HaltCompiler.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-CLASS-PROPERTY
  Scenario: Class Property
    Given PHP "8.1" source "Syntax/ClassProperty.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-CONSTRUCTOR-PROMOTION
  Scenario: Constructor Promotion
    Given PHP "8.1" source "Syntax/ConstructorPromotion.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-PRIVATE-METHOD
  Scenario: Private Method
    Given PHP "8.1" source "Syntax/PrivateMethod.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-PROTECTED-METHOD
  Scenario: Protected Method
    Given PHP "8.1" source "Syntax/ProtectedMethod.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-ABSTRACT-CLASS
  Scenario: Abstract Class
    Given PHP "8.1" source "Syntax/AbstractClass.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-FINAL-CLASS
  Scenario: Final Class
    Given PHP "8.1" source "Syntax/FinalClass.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-INTERFACE-METHOD
  Scenario: Interface Method
    Given PHP "8.1" source "Syntax/InterfaceMethod.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-TRAIT-METHOD
  Scenario: Trait Method
    Given PHP "8.1" source "Syntax/TraitMethod.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-TRAIT-PRECEDENCE
  Scenario: Trait Precedence
    Given PHP "8.1" source "Syntax/TraitPrecedence.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-TRAIT-ALIAS
  Scenario: Trait Alias
    Given PHP "8.1" source "Syntax/TraitAlias.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-LEGACY-PROPERTY
  Scenario: Legacy Property
    Given PHP "8.1" source "Syntax/LegacyProperty.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-NAMESPACE
  Scenario: Namespace
    Given PHP "8.1" source "Syntax/Namespace.php"
    When I derive "Demo\subject#return"
    Then the candidates are:
      | value |
      | "Demo" |

  @syntax @SPEC-SYNTAX-MAGIC-FUNCTION
  Scenario: Magic Function
    Given PHP "8.1" source "Syntax/MagicFunction.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "subject" |

  @syntax @SPEC-SYNTAX-MAGIC-CLASS
  Scenario: Magic Class
    Given PHP "8.1" source "Syntax/MagicClass.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "Box" |

  @syntax @SPEC-SYNTAX-MAGIC-METHOD
  Scenario: Magic Method
    Given PHP "8.1" source "Syntax/MagicMethod.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "Box::read" |

  @syntax @SPEC-SYNTAX-MAGIC-TRAIT
  Scenario: Magic Trait
    Given PHP "8.1" source "Syntax/MagicTrait.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "Value" |

  @syntax @SPEC-SYNTAX-MAGIC-LINE
  Scenario: Magic Line
    Given PHP "8.1" source "Syntax/MagicLine.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 3 |

  @syntax @SPEC-SYNTAX-MAGIC-FILE
  Scenario: Magic File
    Given PHP "8.1" source "Syntax/MagicFile.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-MAGIC-DIRECTORY
  Scenario: Magic Directory
    Given PHP "8.1" source "Syntax/MagicDirectory.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-EXIT-STATEMENT
  Scenario: Exit Statement
    Given PHP "8.1" source "Syntax/ExitStatement.php"
    When I derive "subject#return"
    Then there are no candidates

  @syntax @SPEC-SYNTAX-DIE-STATEMENT
  Scenario: Die Statement
    Given PHP "8.1" source "Syntax/DieStatement.php"
    When I derive "subject#return"
    Then there are no candidates

  @syntax @SPEC-SYNTAX-MAGIC-PROPERTY
  Scenario: Magic Property
    Given PHP "8.4" source "Syntax/MagicProperty.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "value" |
