<?php

use Lagdo\DbAdmin\Driver\Driver;
use Lagdo\DbAdmin\Driver\Sqlite\Engine;
use Lagdo\DbAdmin\Driver\Sqlite\Statement;
use Lagdo\DbAdmin\Driver\Utils\Utils;

Driver::registerBuilder('sqlite', fn(Utils $utils, array $options) =>
    [new Engine($utils, $options), new Statement($utils)]);
