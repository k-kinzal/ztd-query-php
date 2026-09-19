@manual
Feature: Tracking Locations
  Source: GNU Bison Manual, version 3.8.2, as doc/bison.texi of the bison-3.8.2
  release (https://ftp.gnu.org/gnu/bison/bison-3.8.2.tar.xz, also at
  https://cgit.git.savannah.gnu.org/cgit/bison.git/tree/doc/bison.texi?h=v3.8.2).
  Every scenario is tagged with the node of the manual it states, as
  "manual:" followed by the node name with dashes for spaces; online, that
  node is https://www.gnu.org/software/bison/manual/html_node/<node>.html in
  the edition the GNU project publishes (Bison 3.8.1 at the time of writing).
  The nodes stated in this feature:
    Chapter 3, Bison Grammar Files
    3.5.1    Data Type of Locations                       manual:Location-Type
    3.5.2    Actions and Locations                        manual:Actions-and-Locations
    3.7.13   Bison Declaration Summary                    manual:Decl-Summary

  @manual:Location-Type
  Scenario: The location type is set with %define api.location.type and a braced type name
    Given the grammar file:
      """
      %define api.location.type {location_t}
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Define api.location.type = {location_t}
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """

  @manual:Actions-and-Locations
  Scenario: Actions refer to locations with @n and @$, kept verbatim in the code
    Given the grammar file:
      """
      %%
      exp:
        exp '/' exp
          {
            @$.first_column = @1.first_column;
            @$.last_line = @3.last_line;
          }
      ;
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp
        Alternative
          SymbolItem exp
          SymbolItem '/'
          SymbolItem exp
          Action {\n      @$.first_column = @1.first_column;\n      @$.last_line = @3.last_line;\n    }
      """

  @manual:Actions-and-Locations
  Scenario: Locations may also be addressed by named references, @name and @[name]
    Given the grammar file:
      """
      %%
      exp[res]: exp[left] '/' exp[right] { @res = @left; check (@[right]); };
      """
    When the file is parsed
    Then the tree is:
      """
      %%
      Rule exp [res]
        Alternative
          SymbolItem exp [left]
          SymbolItem '/'
          SymbolItem exp [right]
          Action { @res = @left; check (@[right]); }
      """

  @manual:Decl-Summary
  Scenario: %locations requests location processing even when actions do not use @n
    Given the grammar file:
      """
      %locations
      %%
      exp: 'a';
      """
    When the file is parsed
    Then the tree is:
      """
      Flag locations
      %%
      Rule exp
        Alternative
          SymbolItem 'a'
      """
