<?php

namespace SuperAdmin\Admin\RedisManager\DataType;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Arr;
use SuperAdmin\Admin\Widgets\Form;

abstract class DataType
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * Name of the connection.
     *
     * @var string
     */
    protected $conn;

    /**
     * @var Form
     */
    protected $form;

    /**
     * @var array
     */
    public $data;

    /**
     * @param Connection $connection
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
        $this->conn = $connection->getName();
        $this->form = new Form();
        $this->form->action(route('redis-store-key'));
    }

    public function getForm()
    {
        $this->form();

        if (!empty($this->data)) {
            foreach ($this->form->fields() as $field) {
                if ($field->getId() == 'key') {
                    $field->readonly();
                }
            }
        }

        return $this->form->render();
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * phpredis and predis take some arguments differently.
     */
    protected function isPhpRedis(): bool
    {
        return $this->connection instanceof PhpRedisConnection;
    }

    /**
     * Get the value of a key.
     *
     * @return mixed
     */
    abstract public function fetch(string $key);

    /**
     * Create a key.
     *
     * @return mixed
     */
    abstract public function store(array $params);

    /**
     * Replace a key with the submitted value.
     *
     * @return mixed
     */
    public function update(array $params)
    {
        $key = Arr::get($params, 'key');

        $this->getConnection()->del($key);

        return $this->store($params);
    }

    public function form()
    {
    }

    public function ttl($key)
    {
        return $this->getConnection()->ttl($key);
    }

    public function setTtl($key, $expire)
    {
        if (is_null($expire) || $expire === '') {
            return;
        }

        $expire = (int) $expire;

        if ($expire > 0) {
            $this->getConnection()->expire($key, $expire);
        } else {
            $this->getConnection()->persist($key);
        }
    }

    public function prepareData($data)
    {
        return $data;
    }

    public function setData($data = false)
    {
        if ($data) {
            $this->data = $this->prepareData($data);
            $this->form->fill($this->data);
        }

        $this->form->action(route('redis-update-key'));
        $this->form->attribute('method', 'post');

        return $this;
    }

    /**
     * Non-empty values of a list field.
     *
     * @return array<int, string>
     */
    protected function values($values): array
    {
        return array_values(array_filter(array_map('strval', (array) $values), function ($value) {
            return $value !== '';
        }));
    }

    protected function editRedirect($key)
    {
        return redirect(route('redis-edit-key', [
            'conn' => $this->conn,
            'key'  => $key,
        ]));
    }
}
