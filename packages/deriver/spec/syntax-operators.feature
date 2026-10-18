Feature: PHP operators value derivation
  Each scenario captures source and derives a result without executing the fixture.

  @syntax @SPEC-SYNTAX-ARROW-FUNCTION
  Scenario: Arrow Function
    Given PHP "8.1" source "Syntax/ArrowFunction.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-THROW-EXCEPTION
  Scenario: Throw Exception
    Given PHP "8.1" source "Syntax/ThrowException.php"
    When I derive "subject#return"
    Then the candidate has no return value

  @syntax @SPEC-SYNTAX-PRINT-EXPRESSION
  Scenario: Print Expression
    Given PHP "8.1" source "Syntax/PrintExpression.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 1 |

  @syntax @SPEC-SYNTAX-CLONE-OBJECT
  Scenario: Clone Object
    Given PHP "8.1" source "Syntax/CloneObject.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [7,9] |

  @syntax @SPEC-SYNTAX-NEW-OBJECT-IDENTITY
  Scenario: New Object Identity
    Given PHP "8.1" source "Syntax/NewObjectIdentity.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-UNARY-PLUS
  Scenario: Unary Plus
    Given PHP "8.1" source "Syntax/UnaryPlus.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-UNARY-MINUS
  Scenario: Unary Minus
    Given PHP "8.1" source "Syntax/UnaryMinus.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | -7 |

  @syntax @SPEC-SYNTAX-POWER
  Scenario: Power
    Given PHP "8.1" source "Syntax/Power.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 8 |

  @syntax @SPEC-SYNTAX-ADD
  Scenario: Add
    Given PHP "8.1" source "Syntax/Add.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-SUBTRACT
  Scenario: Subtract
    Given PHP "8.1" source "Syntax/Subtract.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-MULTIPLY
  Scenario: Multiply
    Given PHP "8.1" source "Syntax/Multiply.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 12 |

  @syntax @SPEC-SYNTAX-DIVIDE
  Scenario: Divide
    Given PHP "8.1" source "Syntax/Divide.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 4 |

  @syntax @SPEC-SYNTAX-MODULO
  Scenario: Modulo
    Given PHP "8.1" source "Syntax/Modulo.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 2 |

  @syntax @SPEC-SYNTAX-BITWISE-NOT
  Scenario: Bitwise Not
    Given PHP "8.1" source "Syntax/BitwiseNot.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | -8 |

  @syntax @SPEC-SYNTAX-BITWISE-AND
  Scenario: Bitwise And
    Given PHP "8.1" source "Syntax/BitwiseAnd.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 2 |

  @syntax @SPEC-SYNTAX-BITWISE-OR
  Scenario: Bitwise Or
    Given PHP "8.1" source "Syntax/BitwiseOr.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-BITWISE-XOR
  Scenario: Bitwise Xor
    Given PHP "8.1" source "Syntax/BitwiseXor.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 5 |

  @syntax @SPEC-SYNTAX-SHIFT-LEFT
  Scenario: Shift Left
    Given PHP "8.1" source "Syntax/ShiftLeft.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 6 |

  @syntax @SPEC-SYNTAX-SHIFT-RIGHT
  Scenario: Shift Right
    Given PHP "8.1" source "Syntax/ShiftRight.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 2 |

  @syntax @SPEC-SYNTAX-LESS
  Scenario: Less
    Given PHP "8.1" source "Syntax/Less.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-LESS-EQUAL
  Scenario: Less Equal
    Given PHP "8.1" source "Syntax/LessEqual.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-GREATER
  Scenario: Greater
    Given PHP "8.1" source "Syntax/Greater.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-GREATER-EQUAL
  Scenario: Greater Equal
    Given PHP "8.1" source "Syntax/GreaterEqual.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-EQUAL
  Scenario: Equal
    Given PHP "8.1" source "Syntax/Equal.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-NOT-EQUAL
  Scenario: Not Equal
    Given PHP "8.1" source "Syntax/NotEqual.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-NOT-EQUAL-ALTERNATIVE
  Scenario: Not Equal Alternative
    Given PHP "8.1" source "Syntax/NotEqualAlternative.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-IDENTICAL
  Scenario: Identical
    Given PHP "8.1" source "Syntax/Identical.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | false |

  @syntax @SPEC-SYNTAX-NOT-IDENTICAL
  Scenario: Not Identical
    Given PHP "8.1" source "Syntax/NotIdentical.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-SPACESHIP
  Scenario: Spaceship
    Given PHP "8.1" source "Syntax/Spaceship.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | -1 |

  @syntax @SPEC-SYNTAX-BOOLEAN-NOT
  Scenario: Boolean Not
    Given PHP "8.1" source "Syntax/BooleanNot.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | false |

  @syntax @SPEC-SYNTAX-BOOLEAN-AND
  Scenario: Boolean And
    Given PHP "8.1" source "Syntax/BooleanAnd.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | false |

  @syntax @SPEC-SYNTAX-BOOLEAN-OR
  Scenario: Boolean Or
    Given PHP "8.1" source "Syntax/BooleanOr.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-LOGICAL-AND
  Scenario: Logical And
    Given PHP "8.1" source "Syntax/LogicalAnd.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | false |

  @syntax @SPEC-SYNTAX-LOGICAL-OR
  Scenario: Logical Or
    Given PHP "8.1" source "Syntax/LogicalOr.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-LOGICAL-XOR
  Scenario: Logical Xor
    Given PHP "8.1" source "Syntax/LogicalXor.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | false |

  @syntax @SPEC-SYNTAX-CONCATENATION
  Scenario: Concatenation
    Given PHP "8.1" source "Syntax/Concatenation.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "ab" |

  @syntax @SPEC-SYNTAX-COALESCE
  Scenario: Coalesce
    Given PHP "8.1" source "Syntax/Coalesce.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | null |
      | 7 |

  @syntax @SPEC-SYNTAX-TERNARY
  Scenario: Ternary
    Given PHP "8.1" source "Syntax/Ternary.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |
      | 9 |

  @syntax @SPEC-SYNTAX-SHORT-TERNARY
  Scenario: Short Ternary
    Given PHP "8.1" source "Syntax/ShortTernary.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 0 |
      | 7 |

  @syntax @SPEC-SYNTAX-CAST-INT
  Scenario: Cast Int
    Given PHP "8.1" source "Syntax/CastInt.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-CAST-FLOAT
  Scenario: Cast Float
    Given PHP "8.1" source "Syntax/CastFloat.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7.5 |

  @syntax @SPEC-SYNTAX-CAST-STRING
  Scenario: Cast String
    Given PHP "8.1" source "Syntax/CastString.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "7" |

  @syntax @SPEC-SYNTAX-CAST-ARRAY
  Scenario: Cast Array
    Given PHP "8.1" source "Syntax/CastArray.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [7] |

  @syntax @SPEC-SYNTAX-CAST-BOOL
  Scenario: Cast Bool
    Given PHP "8.1" source "Syntax/CastBool.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @syntax @SPEC-SYNTAX-SUPPRESS-ERRORS
  Scenario: Suppress Errors
    Given PHP "8.1" source "Syntax/SuppressErrors.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-CAST-OBJECT
  Scenario: Cast Object
    Given PHP "8.1" source "Syntax/CastObject.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-ASSIGNMENT
  Scenario: Assignment
    Given PHP "8.1" source "Syntax/Assignment.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-PRE-INCREMENT
  Scenario: Pre Increment
    Given PHP "8.1" source "Syntax/PreIncrement.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-POST-INCREMENT
  Scenario: Post Increment
    Given PHP "8.1" source "Syntax/PostIncrement.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 6 |

  @syntax @SPEC-SYNTAX-PRE-DECREMENT
  Scenario: Pre Decrement
    Given PHP "8.1" source "Syntax/PreDecrement.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-POST-DECREMENT
  Scenario: Post Decrement
    Given PHP "8.1" source "Syntax/PostDecrement.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 8 |

  @syntax @SPEC-SYNTAX-ADD-ASSIGN
  Scenario: Add Assign
    Given PHP "8.1" source "Syntax/AddAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-SUBTRACT-ASSIGN
  Scenario: Subtract Assign
    Given PHP "8.1" source "Syntax/SubtractAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-MULTIPLY-ASSIGN
  Scenario: Multiply Assign
    Given PHP "8.1" source "Syntax/MultiplyAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 12 |

  @syntax @SPEC-SYNTAX-DIVIDE-ASSIGN
  Scenario: Divide Assign
    Given PHP "8.1" source "Syntax/DivideAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 4 |

  @syntax @SPEC-SYNTAX-MODULO-ASSIGN
  Scenario: Modulo Assign
    Given PHP "8.1" source "Syntax/ModuloAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 2 |

  @syntax @SPEC-SYNTAX-POWER-ASSIGN
  Scenario: Power Assign
    Given PHP "8.1" source "Syntax/PowerAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 8 |

  @syntax @SPEC-SYNTAX-AND-ASSIGN
  Scenario: And Assign
    Given PHP "8.1" source "Syntax/AndAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 2 |

  @syntax @SPEC-SYNTAX-OR-ASSIGN
  Scenario: Or Assign
    Given PHP "8.1" source "Syntax/OrAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @syntax @SPEC-SYNTAX-XOR-ASSIGN
  Scenario: Xor Assign
    Given PHP "8.1" source "Syntax/XorAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 5 |

  @syntax @SPEC-SYNTAX-SHIFT-LEFT-ASSIGN
  Scenario: Shift Left Assign
    Given PHP "8.1" source "Syntax/ShiftLeftAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 6 |

  @syntax @SPEC-SYNTAX-SHIFT-RIGHT-ASSIGN
  Scenario: Shift Right Assign
    Given PHP "8.1" source "Syntax/ShiftRightAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 2 |

  @syntax @SPEC-SYNTAX-CONCAT-ASSIGN
  Scenario: Concat Assign
    Given PHP "8.1" source "Syntax/ConcatAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "ab" |

  @syntax @SPEC-SYNTAX-COALESCE-ASSIGN
  Scenario: Coalesce Assign
    Given PHP "8.1" source "Syntax/CoalesceAssign.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | null |
      | 7 |

  @syntax @SPEC-SYNTAX-PIPE-OPERATOR
  Scenario: Pipe Operator
    Given PHP "8.5" source "Syntax/PipeOperator.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 6 |

  @syntax @SPEC-SYNTAX-CAST-OBJECT-SCALAR
  Scenario: Cast Object Scalar
    Given PHP "8.1" source "Syntax/CastObjectScalar.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |
