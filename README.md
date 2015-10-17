nrocco/restapi
==============

provides a restful api on top of any database

[![Build Status](https://travis-ci.org/nrocco/restapi.svg?branch=master)](https://travis-ci.org/nrocco/restapi)
[![Coverage Status](https://coveralls.io/repos/nrocco/restapi/badge.svg?branch=master&service=github)](https://coveralls.io/github/nrocco/restapi?branch=master)


installation
------------

To install and start using `nrocco/restapi` clone the repository:

    $ git clone https://github.com/nrocco/restapi.git
    $ cd restapi


If you want to get started right away, you can use make (note: only do this in
dev):

    $ make server


This will use `composer` to download all 3rd party dependencies, create a
`config.php` file with a user `tester` with password `tester` and start the
build in development server on `http://0.0.0.0:8000`.

In the examples below it is assumed you are running the development server
with the sample database schema from the `Tests` folder.

To start the development server run:

    $ make server


The default configuration uses a `sqlite` database located in `app.db`.
In the `Tests` folder there is a `schema.sql` file containing a sample
database schema for a todo app. Load it like this:

    $ sqlite3 app.db < src/RestApi/Tests/schema.sql


usage
-----

With the above schema loaded you get a `/todos` resource. To check all the
available resources do this:

    $ curl --user tester:tester http://0.0.0.0:8000
    HTTP/1.1 200 OK
    Content-Type: application/json

    [
        "categories",
        "todos"
    ]


Let's explore the `/todos` resource:

    $ curl --user tester:tester http://0.0.0.0:8000/todos
    HTTP/1.1 200 OK
    Content-Type: application/json
    X-Pagination-Limit: 25
    X-Pagination-Offset: 0
    X-Pagination-Total: 0

    []

No `todos` yet. So lets create one:

    $ curl --user tester:tester http://0.0.0.0:8000/todos -X POST -d "description=take out the trash"
    HTTP/1.1 200 OK
    Content-Type: application/json

    {
        "id":"1",
        "created":"2015-10-17 20:26:01",
        "updated":"2015-10-17 20:26:01",
        "user_id":"tester",
        "category":"inbox",
        "description":"take out the trash",
        "file":null,
        "done":"0",
        "urgency":"2"
    }

You can do a whole bunch of things on the collection resource such as
`filtering`, `ordering`, `searching`, `projection` and `limit` the results;

The following examples all filter the collection:

    # only list todos from the inbox category
    $ curl --user tester:tester http://0.0.0.0:8000/todos?category=inbox

    # list all todos that contain the word trash in the description
    $ curl --user tester:tester http://0.0.0.0:8000/todos?description__icontains=trash

    # list all todos that have a file
    $ curl --user tester:tester http://0.0.0.0:8000/todos?file__notnull=true

    # list all todos that were created in 2014
    $ curl --user tester:tester http://0.0.0.0:8000/todos?created__year=2014
