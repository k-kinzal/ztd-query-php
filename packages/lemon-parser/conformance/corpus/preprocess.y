%token A B C D E F.
start ::= a.
%ifdef ONE
a ::= A.
%endif
%ifndef ONE
a ::= B.
%endif
%if ONE && TWO
a ::= C.
%else
a ::= D.
%endif
%if !ONE || TWO
  a ::= E.
%ifdef TWO
  a ::= E E.
%else
  a ::= E E E.
%endif
%endif
%if (ONE || TWO) && !THREE
a ::= F.
%endif
%ifdef THREE
%ifdef ONE
a ::= F F.
%endif
%else
a ::= F F F.
%endif
a ::= .
