Feature: Query call paths
  Derive a selected value in matching invocation contexts without executing callers.

  Scenario: Consecutive callers preserve each actual argument tuple
    Given PHP "8.1" source "Query/CallPath.php"
    When I derive "CallPaths\entry -> CallPaths\bar -> CallPaths\foo#return"
    Then the candidates are:
      | value          |
      | "selected:one" |
      | "selected:two" |

  Scenario: An ancestor can be separated by unnamed callers
    Given PHP "8.1" source "Query/CallPath.php"
    When I derive "CallPaths\entry -> ... -> CallPaths\foo#return"
    Then the candidates are:
      | value          |
      | "selected:one" |
      | "selected:two" |

  Scenario: The unspecified outer prefix remains open
    Given PHP "8.1" source "Query/CallPath.php"
    When I derive "CallPaths\bar -> CallPaths\foo#parameter(a)"
    Then the candidates are:
      | value      |
      | "selected" |
      | "other"    |

  Scenario: A direct edge cannot match an indirect call
    Given PHP "8.1" source "Query/CallPath.php"
    When I derive "CallPaths\entry -> CallPaths\foo#return"
    Then there are no candidates

  Scenario: Paths do not enable predicate evaluation
    Given PHP "8.1" source "Query/CallPath.php"
    When I derive "CallPaths\entry -> CallPaths\gate#return"
    Then the candidates are:
      | value |
      | 11    |
      | 27    |

  Scenario: Configured pruning uses the selected invocation inputs
    Given PHP "8.1" source "Query/CallPath.php"
    And branch pruning is enabled
    When I derive "CallPaths\entry -> CallPaths\gate#return"
    Then the candidates are:
      | value |
      | 11    |
