Feature: Runner verification
  Scenario: Passing behavior
    Given a passing step
  Scenario: Failing behavior
    Given a failing step
  Scenario: Undefined behavior
    Given an undefined step
  Scenario Outline: Multiple examples
    Given a passing step
    Examples:
      | example |
      | one     |
      | two     |
