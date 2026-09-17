/*/ still a comment, because the slash after the star does not close it */
%token A B.
// %token NOT_A_TOKEN.
start ::= a. /* trailing */ // trailing
a ::= A /* inline */ B.
a ::= /* empty */ .
