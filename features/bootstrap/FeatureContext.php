<?php

use Lagdo\DbAdmin\Driver\Sqlite\Tests\Driver;

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;

use function count;

class FeatureContext implements Context
{
    /**
     * @var Driver
     */
    protected $driver;

    /**
     * @var array
     */
    protected $databases;

    /**
     * @var int
     */
    protected $dbSize;

    /**
     * The constructor
     */
    public function __construct()
    {
        $this->driver = new Driver();
    }

    /**
     * @Given The default server is connected
     */
    public function connectToTheDefaultServer()
    {
        // Nothing to do
    }

    /**
     * @When I read the database list
     */
    public function getTheDatabaseList()
    {
        $this->databases = $this->driver->databases(true);
    }

    /**
     * @Then There is :count database on the server
     * @Then There are :count databases on the server
     */
    public function checkTheNumberOfDatabases(int $count)
    {
        Assert::assertEquals($count, count($this->databases));
    }

    /**
     * @Then No database query is executed
     */
    public function checkThatNoDatabaseQueryIsExecuted()
    {
        $queries = $this->driver->queries();
        Assert::assertEquals(0, count($queries));
    }

    /**
     * @Given The next request returns :status
     */
    public function setTheNextDatabaseRequestStatus(bool $status)
    {
        $this->driver->connection()->setNextResultStatus($status);
    }

    /**
     * @When I read the database :database size
     */
    public function getTheDatabaseSize(string $database)
    {
        $this->driver->realConnection = true;
        $this->dbSize = $this->driver->databaseSize($database);
        $this->driver->realConnection = false;
    }

    /**
     * @Then The size of the database is :size
     */
    public function checkTheDatabaseSize(int $size)
    {
        Assert::assertEquals($size, $this->dbSize);
    }
}
