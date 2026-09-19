@manual
Feature: %code Summary
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 3, Bison Grammar Files
    3.7.15   %code Summary                                manual:_0025code-Summary

  @manual:_0025code-Summary
  Scenario: The unqualified form inserts code at the default location
    Given the grammar file:
      """
      %code {
        static void print_token (yytoken_kind_t token, YYSTYPE val);
      }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Code {\n  static void print_token (yytoken_kind_t token, YYSTYPE val);\n}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:_0025code-Summary
  Scenario Outline: The qualified form names the purpose of the code with <qualifier>
    Given the grammar file:
      """
      %code <qualifier> { <code> }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Code <qualifier> { <code> }
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

    Examples:
      | qualifier | code                       |
      | requires  | #include "ptypes.h"        |
      | provides  | void trace_token (void);   |
      | top       | #define _GNU_SOURCE        |
      | imports   | import java.util.List;     |

  @manual:_0025code-Summary
  Scenario: Several occurrences of %code with the same qualifier are kept in the order they appear
    Given the grammar file:
      """
      %code requires { #include "type1.h" }
      %code requires { #include "type2.h" }
      %code { static int a; }
      %code { static int b; }
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Code requires { #include "type1.h" }
      Code requires { #include "type2.h" }
      Code { static int a; }
      Code { static int b; }
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:_0025code-Summary
  Scenario: %code requires braced code
    Given the grammar file:
      """
      %code requires
      %%
      exp: 'a';
      """
    When the file is parsed
    Then parsing fails at line 2 column 1
