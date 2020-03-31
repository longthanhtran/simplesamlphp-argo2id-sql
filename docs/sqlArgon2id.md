`sqlauthArgon2id:SQL`
=============

This is a authentication module for authenticating a user against a SQL database using Argo2Id password.


Options
-------

`dsn`
:   The DSN which should be used to connect to the database server.
    Check the various database drivers in the [PHP documentation](http://php.net/manual/en/pdo.drivers.php) for a description of the various DSN formats.

`username`
:   The username which should be used when connecting to the database server.


`password`
:   The password which should be used when connecting to the database server.

`query`
:   The SQL query which should be used to retrieve the user.
    The parameters :username and :password are available.
    If the username/password is incorrect, the query should return no rows.
    The name of the columns in resultset will be used as attribute names.
    If the query returns multiple rows, they will be merged into the attributes.
    Duplicate values and NULL values will be removed.


Examples
--------

Database layout used in some of the examples:

* MySQL
```sql
    CREATE TABLE users (
        uid VARCHAR(30) NOT NULL PRIMARY KEY,
        password TEXT NOT NULL,
        enabled BOOLEAN DEFAULT false,
        fullname TEXT NOT NULL,
        email TEXT NOT NULL,
        last_login DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    CREATE TABLE usergroups (
      uid VARCHAR(30) NOT NULL REFERENCES users (uid) ON DELETE CASCADE ON UPDATE CASCADE,
      groupname VARCHAR(30) NOT NULL,
      UNIQUE(uid, groupname)
    );
```

* PostgreSQL
```sql
    CREATE TABLE users (
        uid text PRIMARY KEY,
        password text NOT NULL,
        enabled boolean DEFAULT false,
        fullname text NOT NULL,
        email text NOT NULL,
        last_login timestamp with time zone,
        created_at timestamp with time zone NOT NULL DEFAULT NOW(),
        updated_at timestamp with time zone NOT NULL DEFAULT NOW()
    );

    CREATE UNIQUE INDEX users_pkey ON users(uid text_ops);

    CREATE OR REPLACE FUNCTION trigger_set_timestamp()
    RETURNS TRIGGER AS $$
    BEGIN
        NEW.updated_at = NOW();
        RETURN NEW;
    END;
    $$ LANGUAGE plpgsql;

    CREATE TRIGGER set_timestamp
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE PROCEDURE trigger_set_timestamp();

    CREATE TABLE usergroups (
        uid text REFERENCES users(uid) ON DELETE CASCADE,
        groupname text NOT NULL,
        created_at timestamp with time zone DEFAULT NOW()
    );

    CREATE UNIQUE INDEX usergroups_uid_groupnames ON usergroups(uid text_ops,groupname text_ops);
```

Example query - username + (argo2id) password MySQL / PostgreSQL server:

```sql
    SELECT users.uid, users.fullname, users.email, users.enabled, usergroups.groupname
        FROM users LEFT JOIN usergroups ON users.uid = usergroups.uid
        WHERE
            users.uid = :username
            AND users.password = :password;
```


DataSource example
------------------
```sql
    'bcrypt-example' => array(
      'sqlauthbcrypt:SQL',
      'dsn' => 'mysql:host=sql.example.org;dbname=idp',
      'username' => 'userdb',
      'password' => 'idp_userdb_passw0rD',
      'query' => 'SELECT username AS uid, name AS cn, email AS mail, password_hash FROM users WHERE username = :username'
    )
```

Security considerations
-----------------------

Consider to check against both `enabled` and `groupname` after first round check with `username` and (argo2id) `password`.
This will enable precise application login as nature of IDentity Provider (IDP).
