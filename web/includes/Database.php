<?php

class Database
{
    private $prefix;
    private $dbh;
    /** @var $stmt PDOStatement */
    private $stmt;

    public function __construct($host, $port, $dbname, $user, $password, $prefix, $charset = 'utf8')
    {
        $this->prefix = $prefix;
        $dsn = 'mysql:host='.$host.';port='.$port.';dbname='.$dbname.';charset='.$charset;
        $options = array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        );

        try {
            $this->dbh = new \PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }

    public function __destruct()
    {
        unset($this->dbh);
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    private function setPrefix($query)
    {
        $query = str_replace(':prefix', $this->prefix, $query);
        return $query;
    }

    public function query($query)
    {
        $query = $this->setPrefix($query);
        $this->stmt = $this->dbh->prepare($query);
    }

    public function bind($param, $value, $type = null)
    {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = \PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = \PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = \PDO::PARAM_NULL;
                    break;
                default:
                    $type = \PDO::PARAM_STR;
            }
        }

        $this->stmt->bindValue($param, $value, $type);
    }

    public function bindMultiple($params = [])
    {
        foreach ($params as $key => $value) {
            $this->bind($key, $value);
        }
    }

    public function execute()
    {
        return $this->stmt->execute();
    }

    public function resultset()
    {
        $this->execute();
        return $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function single()
    {
        $this->execute();
        return $this->stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    public function lastInsertId()
    {
        return $this->dbh->lastInsertId();
    }

    public function beginTransaction()
    {
        return $this->dbh->beginTransaction();
    }

    public function endTransaction()
    {
        return $this->dbh->commit();
    }

    public function cancelTransaction()
    {
        return $this->dbh->rollBack();
    }

    public function debugDumpParams()
    {
        return $this->stmt->debugDumpParams();
    }
}
