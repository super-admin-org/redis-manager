<?php

namespace SuperAdmin\Admin\RedisManager\DataType;

use Illuminate\Support\Arr;

class Hashes extends DataType
{
    public function fetch(string $key)
    {
        return $this->getConnection()->hgetall($key);
    }

    public function store(array $params)
    {
        $key = Arr::get($params, 'key');
        $ttl = (int) Arr::get($params, 'ttl', -1);

        // The key-value field posts value[keys][] and value[values][].
        $fields = (array) Arr::get($params, 'value.keys', []);
        $values = (array) Arr::get($params, 'value.values', []);

        foreach (array_values($fields) as $i => $field) {
            if ((string) $field === '') {
                continue;
            }

            $this->getConnection()->hset($key, $field, (string) ($values[$i] ?? ''));
        }

        if ($ttl > 0) {
            $this->getConnection()->expire($key, $ttl);
        }

        return $this->editRedirect($key);
    }

    public function remove(array $params)
    {
        $key = Arr::get($params, 'key');
        $field = Arr::get($params, 'field');

        return $this->getConnection()->hdel($key, $field);
    }

    public function form()
    {
        $this->form->hidden('conn')->value($this->conn);
        $this->form->hidden('type')->value('hash');
        $this->form->text('key');
        $this->form->number('ttl')->default(-1);
        $this->form->keyValue('value');
    }
}
