<?php

if(class_exists(Lagdo\Adminer\DbAdmin::class))
{
    Lagdo\Adminer\DbAdmin::addServer("sqlite", Lagdo\DbAdmin\Driver\Sqlite\Server::class);
}
