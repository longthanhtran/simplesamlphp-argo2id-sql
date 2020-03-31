<?php

namespace SimpleSAML\Module\sqlauthargon2id\Auth\Source;

use \SimpleSAML\Module\core\auth\UserPassBase;
use \SimpleSAML\Logger;
use \SimpleSAML\Error\Error as SimpleSAMLError;

class SQL extends UserPassBase {

    private $dsn;
    private $username;
    private $password;
    private $options;
    private $query;

    public function __construct($info, $config) {
        assert(is_array($info));
        assert(is_array($config));
        $this->phpVersionCheck();

        parent::__construct($info, $config);
        verifyParams($config);
        buildProperties($config);
    }

    private function phpVersionCheck() {
        if(!version_compare(PHP_VERSION, '7.3.0') >= 0) {
            throw new Exception('Require at least PHP 7.3.0 for Argon2id');
        }
    }

    private function buildProperties($config) {
        $this->dsn      = $config['dsn'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->query    = $config['query'];
        if (isset($config['options'])) {
            $this->options = $config['options'];
        }
    }

    private function verifyParams($config) {
        foreach (['dsn', 'username', 'password', 'query'] as $param) {
            if (!array_key_exists($param, $config)) {
                throw new \Exception('Missing required attribute \''.$param.
                    '\' for authentication source '.$this->authId);
            }

            if (!is_string($config[$param])) {
                throw new \Exception('Expected parameter \''.$param.
                    '\' for authentication source '.$this->authId.
                    ' to be a string. Instead it was: '.
                    var_export($config[$param], true));
            }
        }
    }

    private function connect() {
        try {
            $db = new \PDO($this->dsn, $this->username, $this->password, $this->options);
        } catch (\PDOException $e) {
            throw new \Exception('sqlauth:'.$this->authId.': - Failed to connect to \''.
                $this->dsn.'\': '.$e->getMessage());
        }

        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $driver = explode(':', $this->dsn, 2);
        $driver = strtolower($driver[0]);

        // Driver specific initialization
        switch ($driver) {
            case 'mysql':
                // Use UTF-8
                $db->exec("SET NAMES 'utf8mb4'");
                break;
            case 'pgsql':
                // Use UTF-8
                $db->exec("SET NAMES 'UTF8'");
                break;
        }

        return $db;
    }

    protected function login($username, $password) {
        assert(is_string($username));
        assert(is_string($password));

        $db = $this->connect();

        $data = queryUserAccount($db, $username);

        Logger::info('sqlauthArgon2Id:' . $this->authId .
            ': Got ' . count($data) . ' rows from database');

        if (count($data) === 0) {
            /* No rows returned - invalid username */
            Logger::error('sqlauthArgon2Id:' . $this->authId .
                ': No rows in result set. Wrong username or sqlauthArgon2Id is misconfigured.');
            throw new SimpleSAMLError('WRONGUSERPASS');
        }

        /* Validate stored password hash (must be in first row of resultset) */
        $encrypted_password = $data[0]['password'];
        $enabled = $data[0]['enabled'];

        if (!$enabled && !password_verify($password, $encrypted_password)) {
            /* Invalid password */
            Logger::error('sqlauthArgon2Id:' . $this->authId .
                ': Account is inactived or wrong password or sqlauthArgon2Id is misconfigured.');
            throw new SimpleSAMLError('WRONGUSERPASS');
        }
        $attributes = buildUserAttributes($data);

        Logger::info('sqlauthArgon2Id:'.$this->authId.': Attributes: '.
            implode(',', array_keys($attributes)));

        return $attributes;
    }

    private function buildUserAttributes($data) {
        $attributes = [];
        foreach ($data as $row) {
            foreach ($row as $name => $value) {
                if ($value === null) {
                    continue;
                }

                $value = (string) $value;

                if (!array_key_exists($name, $attributes)) {
                    $attributes[$name] = [];
                }

                if (in_array($value, $attributes[$name], true)) {
                    // Value already exists in attribute
                    continue;
                }

                $attributes[$name][] = $value;
            }
        }

        return $attributes;
    }

    private function queryUserAccount($db, $username) {
        try {
            $sth = $db->prepare($this->query);
        } catch (PDOException $e) {
            throw new Exception('sqlauthArgon2Id:' . $this->authId .
                ': - Failed to prepare query: ' . $e->getMessage());
        }

        try {
            $res = $sth->execute(array('username' => $username));
        } catch (PDOException $e) {
            throw new Exception('sqlauthArgon2Id:' . $this->authId .
                ': - Failed to execute query: ' . $e->getMessage());
        }

        try {
            return $sth->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('sqlauth:' . $this->authId .
                ': - Failed to fetch result set: ' . $e->getMessage());
        }
    }

}