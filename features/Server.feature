Feature: Database server
  As a database user, I want to query a database server

  Scenario: Read the database names
    Given The default server is connected
    When I read the database list
    Then 0 database query is executed
    Then There is 1 database on the server

  Scenario: Read the size of an unknown database
    Given The default server is connected
    When I read the database unknown.sdb size
    Then 0 database query is executed
    Then The size of the database is 0

  Scenario: Read the size of an existing database
    Given The default server is connected
    When I read the database test.sdb size
    Then 0 database query is executed
    Then The size of the database is 3072

  Scenario: Create a database
    Given The default server is connected
    When I create the database test1.sdb
    And I read the database list
    Then There are 2 databases on the server

  Scenario: Rename a database
    Given The default server is connected
    When I open the database test1.sdb
    And I rename the database to test2.sdb
    And I read the database list
    Then There are 2 databases on the server

  Scenario: Delete a database
    Given The default server is connected
    When I open the database test.sdb
    And I delete the database test2.sdb
    And I read the database list
    Then There is 1 database on the server
