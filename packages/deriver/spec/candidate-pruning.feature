Feature: Finite candidate coverage and structural loop pruning
  Candidate derivation stops at complete finite coverage.
  Optional predicate evaluators prune structural candidates without executing loops.

  Scenario: Boolean coverage leaves later dependencies unopened
    Given PHP "8.1" source "Candidate/Finite.php"
    When I derive "FiniteCandidates\subject#return"
    Then the candidates are:
      | value |
      | false |
      | true  |
    And the body "FiniteCandidates\expensive" is not expanded

  Scenario: Default loop derivation keeps structural candidates
    Given PHP "8.1" source "Candidate/Loops.php"
    When I derive "LoopCandidates\subject#return"
    Then the candidates are:
      | value |
      | 0     |
      | 11    |
      | 27    |

  Scenario: A one-pass constraint prunes body alternatives
    Given PHP "8.1" source "Candidate/Loops.php"
    And branch pruning is enabled
    When I derive "LoopCandidates\subject#return"
    Then the candidates are:
      | value |
      | 11    |

  Scenario: Unknown predicates preserve independent candidate products
    Given PHP "8.1" source "Candidate/Loops.php"
    And branch pruning is enabled
    When I derive "LoopCandidates\independent#return"
    Then the candidates are:
      | value |
      | 12    |
      | 15    |
      | 28    |
      | 31    |
