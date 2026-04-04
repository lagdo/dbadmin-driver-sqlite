<?php

namespace Lagdo\DbAdmin\Support\Sqlite\Tests;

use Lagdo\DbAdmin\Support\Utils\Str;
use Lagdo\DbAdmin\Support\Utils\Utils;
use Lagdo\DbAdmin\Support\Utils\Input;
use Lagdo\DbAdmin\Support\Db\Fake\DriverTrait;
use Lagdo\DbAdmin\Support\Db\Fake\Translator;
use Lagdo\DbAdmin\Support\Sqlite\Driver as SqliteDriver;

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
