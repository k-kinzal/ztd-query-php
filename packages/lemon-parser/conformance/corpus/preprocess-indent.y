// A directive counts only at the start of a line, even inside an excluded region.
%token A.
%ifdef NEVER
  %ifdef ALSO_NEVER
%endif
start ::= A.
