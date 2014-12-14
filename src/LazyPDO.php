<?php

namespace F3\LazyPDO;

use Serializable;
use PDO;
use RuntimeException;

/**
 * LazyPDO does not instanciate real PDO until it is really needed
 *
 * Also it can be (un)serialized
 *
 * @version $id$
 * @author Alexey Karapetov <karapetov@gmail.com>
 * @author Stephen Leavitt <stephen.leavitt@sonyatv.com>
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */
class LazyPDO extends PDODecorator implements Serializable
{
    private $dsn;
    private $user;
    private $password;
    private $lazy_options = array();
    private $options = array();

    private $pdo = null;

    /**
     * __construct
     *
     * @param string $dsn
     * @param string $user
     * @param string $password
     * @param array $options
     */
    public function __construct($dsn, $user = null, $password = null, array $options = array())
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->options = $options;
    }

    /**
     * Get PDO object. Cache the result
     *
     * @return PDO
     */
    protected function getPDO()
    {
        if (null === $this->pdo) {
            $this->pdo = new PDO($this->dsn, $this->user, $this->password, $this->options);

            if (!empty($this->lazy_options)) {
                foreach($this->lazy_options as $attribute => $value) {
                    if ($this->pdo->setAttribute($attribute, $value)) {
                        $this->options[$attribute] = $value;
                    }
                }

                $this->lazy_options = array();
            }
        }

        return $this->pdo;
    }

    /**
     * Checks if inside a transaction
     *
     * @return bool
     */
    public function inTransaction()
    {
        // Do not call parent method if there is no pdo object
        return $this->pdo && parent::inTransaction();
    }

    /**
     * serialize
     *
     * @return string
     */
    public function serialize()
    {
        if ($this->inTransaction()) {
            throw new RuntimeException('Can not serialize in transaction');
        }
        return serialize(array(
            $this->dsn,
            $this->user,
            $this->password,
            $this->options,
        ));
    }

    /**
     * unserialize
     *
     * @param string $serialized
     * @return void
     */
    public function unserialize($serialized)
    {
        list($this->dsn, $this->user, $this->password, $this->options) = unserialize($serialized);
    }

    /**
     * getAttribute
     *
     * @param int $attribute
     * @return mixed
     */
    public function getAttribute($attribute)
    {
        if ($this->pdo instanceof \PDO) {
            return parent::getAttribute($attribute);
        }

        return isset($this->lazy_options[$attribute]) ? $this->lazy_options[$attribute] : null;
    }

    /**
     * setAttribute
     *
     * @param int $attribute
     * @param mixed $value
     * @return boolean
     */
    public function setAttribute($attribute, $value)
    {
        if ($this->pdo instanceof \PDO) {
            if (parent::setAttribute($attribute, $value)) {
                $this->options[$attribute] = $value;

                return true;
            }

            return false;
        }

        $this->lazy_options[$attribute] = $value;

        return true;
    }
}
