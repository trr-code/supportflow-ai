<?php

namespace Tests;

abstract class EvalTestCase extends TestCase
{
    #[\Override]
    protected bool $fakeEmbeddings = false;
}
