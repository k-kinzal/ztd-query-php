Feature: Tracking Locations
  GNU Bison 3.8.2 manual, chapter "Bison Grammar Files", section "Tracking
  Locations" with its subsections "Data Type of Locations" and "Actions and
  Locations".

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
