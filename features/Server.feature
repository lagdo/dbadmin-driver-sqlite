Feature: Database server
  As a database user, I want to query a database server

  Scenario: Read the database names
    Given The default server is connected
    When I read the database list
    Then No database query is executed
    Then There is 1 database on the server

  Scenario: Read the size of an unknown database
    Given The default server is connected
    When I read the database unknown.sdb size
    Then No database query is executed
    Then The size of the database is 0

  Scenario: Read the size of an existing database
    Given The default server is connected
    When I read the database test.sdb size
    Then No database query is executed
    Then The size of the database is 3072
