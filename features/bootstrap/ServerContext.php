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
     * @var mixed
     */
    protected $dbResult;

    /**
     * @Given The default server is connected
     */
    public function connectToTheDefaultServer()
    {
        $this->driver = new Driver();
    }

    /**
     * @Given The database :database is connected
     */
    public function connectToTheDatabase(string $database)
    {
        $this->driver->open($database);
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
     * @Then The size of the database is :size
     */
    public function checkTheDatabaseSize(int $size)
    {
        Assert::assertEquals($size, $this->dbSize);
    }

    /**
     * @Then The operation has succeeded
     * @Then The result is true
     */
    public function checkThatTheOperationHasSucceeded()
    {
        Assert::assertTrue($this->dbResult === true);
    }

    /**
     * @Then The operation has failed
     * @Then The result is false
     */
    public function checkThatTheOperationHasFailed()
    {
        Assert::assertTrue($this->dbResult === false);
    }

    /**
     * @Then The result is an array with :count item
     * @Then The result is an array with :count items
     */
    public function checkThatTheResultIsAnArrayWithCount(int $count)
    {
        Assert::assertTrue(is_array($this->dbResult));
        Assert::assertTrue(count($this->dbResult) === $count);
    }

    /**
     * @Then The result is the string :value
     */
    public function checkThatTheResultIsAStringWithValue(string $value)
    {
        Assert::assertTrue(is_string($this->dbResult));
        Assert::assertEquals($value, $this->dbResult);
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
        $this->driver->open($database, '');
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
     * @When I get the collation of the database
     */
    public function getTheCurrentDatabaseCollation()
    {
        $this->dbResult = $this->driver->databaseCollation('', []);
    }
}
