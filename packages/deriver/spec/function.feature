Feature: Built-in function value derivation
  Each scenario captures source and derives a result without executing the fixture.

  @function @SPEC-FUNCTION-STRLEN
  Scenario: Strlen
    Given PHP "8.1" source "Function/Strlen.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 5 |

  @function @SPEC-FUNCTION-STRTOLOWER
  Scenario: Strtolower
    Given PHP "8.1" source "Function/Strtolower.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "hello" |

  @function @SPEC-FUNCTION-STRTOUPPER
  Scenario: Strtoupper
    Given PHP "8.1" source "Function/Strtoupper.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "HELLO" |

  @function @SPEC-FUNCTION-UCFIRST
  Scenario: Ucfirst
    Given PHP "8.1" source "Function/Ucfirst.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "Hello" |

  @function @SPEC-FUNCTION-LCFIRST
  Scenario: Lcfirst
    Given PHP "8.1" source "Function/Lcfirst.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "hello" |

  @function @SPEC-FUNCTION-TRIM
  Scenario: Trim
    Given PHP "8.1" source "Function/Trim.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "hello" |

  @function @SPEC-FUNCTION-LTRIM
  Scenario: Ltrim
    Given PHP "8.1" source "Function/Ltrim.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "hello  " |

  @function @SPEC-FUNCTION-RTRIM
  Scenario: Rtrim
    Given PHP "8.1" source "Function/Rtrim.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "  hello" |

  @function @SPEC-FUNCTION-IMPLODE
  Scenario: Implode
    Given PHP "8.1" source "Function/Implode.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "a-b" |

  @function @SPEC-FUNCTION-JOIN
  Scenario: Join
    Given PHP "8.1" source "Function/Join.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "a-b" |

  @function @SPEC-FUNCTION-EXPLODE
  Scenario: Explode
    Given PHP "8.1" source "Function/Explode.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | ["a","b"] |

  @function @SPEC-FUNCTION-SUBSTR
  Scenario: Substr
    Given PHP "8.1" source "Function/Substr.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "ell" |

  @function @SPEC-FUNCTION-SPRINTF
  Scenario: Sprintf
    Given PHP "8.1" source "Function/Sprintf.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "item:7" |

  @function @SPEC-FUNCTION-VSPRINTF
  Scenario: Vsprintf
    Given PHP "8.1" source "Function/Vsprintf.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "item:7" |

  @function @SPEC-FUNCTION-STR-REPLACE
  Scenario: Str Replace
    Given PHP "8.1" source "Function/StrReplace.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "cbt" |

  @function @SPEC-FUNCTION-STR-REPEAT
  Scenario: Str Repeat
    Given PHP "8.1" source "Function/StrRepeat.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "abab" |

  @function @SPEC-FUNCTION-STRVAL
  Scenario: Strval
    Given PHP "8.1" source "Function/Strval.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "7" |

  @function @SPEC-FUNCTION-INTVAL
  Scenario: Intval
    Given PHP "8.1" source "Function/Intval.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 255 |

  @function @SPEC-FUNCTION-IS-ARRAY
  Scenario: Is Array
    Given PHP "8.1" source "Function/IsArray.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-IS-STRING
  Scenario: Is String
    Given PHP "8.1" source "Function/IsString.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-IS-INT
  Scenario: Is Int
    Given PHP "8.1" source "Function/IsInt.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-IS-INTEGER
  Scenario: Is Integer
    Given PHP "8.1" source "Function/IsInteger.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-IS-FLOAT
  Scenario: Is Float
    Given PHP "8.1" source "Function/IsFloat.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-IS-DOUBLE
  Scenario: Is Double
    Given PHP "8.1" source "Function/IsDouble.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-IS-BOOL
  Scenario: Is Bool
    Given PHP "8.1" source "Function/IsBool.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-IS-NULL
  Scenario: Is Null
    Given PHP "8.1" source "Function/IsNull.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-IS-OBJECT
  Scenario: Is Object
    Given PHP "8.1" source "Function/IsObject.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-IS-NUMERIC
  Scenario: Is Numeric
    Given PHP "8.1" source "Function/IsNumeric.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-IS-SCALAR
  Scenario: Is Scalar
    Given PHP "8.1" source "Function/IsScalar.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-IS-CALLABLE
  Scenario: Is Callable
    Given PHP "8.1" source "Function/IsCallable.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-COUNT
  Scenario: Count
    Given PHP "8.1" source "Function/Count.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 3 |

  @function @SPEC-FUNCTION-ARRAY-FILL
  Scenario: Array Fill
    Given PHP "8.1" source "Function/ArrayFill.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | ["a","a"] |

  @function @SPEC-FUNCTION-ARRAY-VALUES
  Scenario: Array Values
    Given PHP "8.1" source "Function/ArrayValues.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [1,2] |

  @function @SPEC-FUNCTION-ARRAY-KEYS
  Scenario: Array Keys
    Given PHP "8.1" source "Function/ArrayKeys.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | ["x","y"] |

  @function @SPEC-FUNCTION-ARRAY-MERGE
  Scenario: Array Merge
    Given PHP "8.1" source "Function/ArrayMerge.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [1,2] |

  @function @SPEC-FUNCTION-IN-ARRAY
  Scenario: In Array
    Given PHP "8.1" source "Function/InArray.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-ARRAY-KEY-EXISTS
  Scenario: Array Key Exists
    Given PHP "8.1" source "Function/ArrayKeyExists.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | true |

  @function @SPEC-FUNCTION-ARRAY-KEY-FIRST
  Scenario: Array Key First
    Given PHP "8.1" source "Function/ArrayKeyFirst.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "x" |

  @function @SPEC-FUNCTION-ARRAY-KEY-LAST
  Scenario: Array Key Last
    Given PHP "8.1" source "Function/ArrayKeyLast.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "y" |

  @function @SPEC-FUNCTION-ARRAY-SLICE
  Scenario: Array Slice
    Given PHP "8.1" source "Function/ArraySlice.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [2] |

  @function @SPEC-FUNCTION-ARRAY-MAP
  Scenario: Array Map
    Given PHP "8.1" source "Function/ArrayMap.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [2,4] |

  @function @SPEC-FUNCTION-ARRAY-FILTER
  Scenario: Array Filter
    Given PHP "8.1" source "Function/ArrayFilter.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | {"1":2,"2":3} |

  @function @SPEC-FUNCTION-ARRAY-REDUCE
  Scenario: Array Reduce
    Given PHP "8.1" source "Function/ArrayReduce.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 3 |

  @function @SPEC-FUNCTION-CALL-USER-FUNC
  Scenario: Call User Func
    Given PHP "8.1" source "Function/CallUserFunc.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @function @SPEC-FUNCTION-CALL-USER-FUNC-ARRAY
  Scenario: Call User Func Array
    Given PHP "8.1" source "Function/CallUserFuncArray.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |

  @function @SPEC-FUNCTION-GET-CLASS
  Scenario: Get Class
    Given PHP "8.1" source "Function/GetClass.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "stdClass" |

  @function @SPEC-FUNCTION-SORT
  Scenario: Sort
    Given PHP "8.1" source "Function/Sort.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [1,2,3] |

  @function @SPEC-FUNCTION-USORT
  Scenario: Usort
    Given PHP "8.1" source "Function/Usort.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [1,2,3] |

  @function @SPEC-FUNCTION-ARRAY-SHIFT
  Scenario: Array Shift
    Given PHP "8.1" source "Function/ArrayShift.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 1 |

  @function @SPEC-FUNCTION-ARRAY-POP
  Scenario: Array Pop
    Given PHP "8.1" source "Function/ArrayPop.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 2 |

  @function @SPEC-FUNCTION-ARRAY-PUSH
  Scenario: Array Push
    Given PHP "8.1" source "Function/ArrayPush.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [1,2] |

  @function @SPEC-FUNCTION-ARRAY-UNSHIFT
  Scenario: Array Unshift
    Given PHP "8.1" source "Function/ArrayUnshift.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | [1,2] |

  @function @SPEC-FUNCTION-GETENV
  Scenario: Getenv
    Given PHP "8.1" source "Function/Getenv.php"
    And environment "APP_ENV" equals '"test"'
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | "test" |

  @function @SPEC-FUNCTION-DEFINE
  Scenario: Define
    Given PHP "8.1" source "Function/Define.php"
    When I derive "subject#return"
    Then the candidates are:
      | value |
      | 7 |
