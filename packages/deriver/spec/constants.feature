Feature: Standard constants in captured PHP source
  Constant values are resolved independently of control-flow predicate evaluation.

  @constant @SPEC-CONSTANT-TRUE
  Scenario: True in a namespace
    Given PHP "8.1" source "Constant/True.php"
    When I derive "Constants\subject#return"
    Then the candidates are:
      | value |
      | [true,true,true,true] |

  @constant @SPEC-CONSTANT-FALSE
  Scenario: False in a namespace
    Given PHP "8.1" source "Constant/False.php"
    When I derive "Constants\subject#return"
    Then the candidates are:
      | value |
      | [false,false,false,false] |

  @constant @SPEC-CONSTANT-NULL
  Scenario: Null in a namespace
    Given PHP "8.1" source "Constant/Null.php"
    When I derive "Constants\subject#return"
    Then the candidates are:
      | value |
      | [null,null,null,null] |

  @constant @SPEC-CONSTANT-PHP-INT-SIZE
  Scenario: IntegerSize in a namespace
    Given PHP "8.1" source "Constant/IntegerSize.php"
    When I derive "Constants\subject#return"
    Then the candidates are:
      | value |
      | [8,8] |

  @constant @SPEC-CONSTANT-PHP-INT-MAX
  Scenario: IntegerMaximum in a namespace
    Given PHP "8.1" source "Constant/IntegerMaximum.php"
    When I derive "Constants\subject#return"
    Then the candidates are:
      | value |
      | [9223372036854775807,9223372036854775807] |

  @constant @SPEC-CONSTANT-PHP-INT-MIN
  Scenario: IntegerMinimum in a namespace
    Given PHP "8.1" source "Constant/IntegerMinimum.php"
    When I derive "Constants\subject#return"
    Then the candidates are:
      | value |
      | [-9223372036854775808,-9223372036854775808] |
