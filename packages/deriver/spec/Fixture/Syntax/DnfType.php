<?php

interface A {} interface B {} class Both implements A, B {}
function value((A&B)|null $value): int { return 7; }
function subject() { return value(new Both()); }
