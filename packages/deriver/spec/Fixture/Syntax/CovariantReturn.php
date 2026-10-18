<?php

class Base {} class Child extends Base { public int $value = 7; } interface Factory { public function make(): Base; } class Concrete implements Factory { public function make(): Child { return new Child(); } } function subject() { return (new Concrete())->make()->value; }
