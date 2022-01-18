<?php

use Lagdo\DbAdmin\Driver\Sqlite\Tests\Driver;

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;

class ServerContext implements Context
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
     * @var bool
     */
    protected $dbResult;

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
        $this->driver->createConnection();
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
        Assert::assertEquals('', $this->driver->error());
        Assert::assertEquals($count, count($this->databases));
    }

    /**
     * @Then :count database query is executed
     * @Then :count database queries are executed
     */
    public function checkTheNumberOfDatabaseQueries(int $count)
    {
        Assert::assertEquals($count, count($this->driver->queries()));
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
        $this->dbSize = $this->driver->databaseSize($database);
    }

    /**
     * @Then The size of the database is :size
     */
    public function checkTheDatabaseSize(int $size)
    {
        Assert::assertEquals($size, $this->dbSize);
    }

    /**
     * @When I create the database :database
     */
    public function createDatabase(string $database)
    {
        $this->dbResult = $this->driver->createDatabase($database, '');
    }

    /**
     * @When I open the database :database
     */
    public function openDatabase(string $database)
    {
        $this->driver->connect($database, '');
    }

    /**
     * @When I rename the database to :database
     */
    public function renameDatabase(string $database)
    {
        $this->dbResult = $this->driver->renameDatabase($database, '');
    }

    /**
     * @When I delete the database :database
     */
    public function deleteDatabase(string $database)
    {
        $this->dbResult = $this->driver->dropDatabase($database);
    }

    /**
     * @Then The operation has succeeded
     */
    public function checkThatTheOperationHasSucceeded()
    {
        Assert::assertTrue($this->dbResult);
    }

    /**
     * @Then The operation has failed
     */
    public function checkThatTheOperationHasFailed()
    {
        Assert::assertFalse($this->dbResult);
    }
}
