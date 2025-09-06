<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Tests;

use Lagdo\DbAdmin\Driver\Utils\Str;
use Lagdo\DbAdmin\Driver\Utils\Utils;
use Lagdo\DbAdmin\Driver\Utils\Input;
use Lagdo\DbAdmin\Driver\Fake\DriverTrait;
use Lagdo\DbAdmin\Driver\Fake\Translator;
use Lagdo\DbAdmin\Driver\Sqlite\Driver as SqliteDriver;

class Driver extends SqliteDriver
{
    use DriverTrait;

    /**
     * The constructor
     */
    public function __construct()
    {
        $utils = new Utils(new Translator(), new Input(), new Str());
        parent::__construct($utils, [
            'directory' => __DIR__ . '/databases',
        ]);
    }
}
