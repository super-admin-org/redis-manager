<?php

namespace SuperAdmin\Admin\RedisManager\DataType;

use Illuminate\Support\Arr;

class Lists extends DataType
{
    public function fetch(string $key)
    {
        return $this->getConnection()->lrange($key, 0, -1);
    }

    public function store(array $params)
    {
        $key = Arr::get($params, 'key');
        $values = $this->values(Arr::get($params, 'value'));
        $ttl = (int) Arr::get($params, 'ttl', -1);

        if ($values) {
            $this->getConnection()->rpush($key, ...$values);
        }

        if ($ttl > 0) {
            $this->getConnection()->expire($key, $ttl);
        }

        return $this->editRedirect($key);
    }

    public function remove(array $params)
    {
        $key = Arr::get($params, 'key');
        $index = (int) Arr::get($params, 'index');

        $lua = <<<'LUA'
redis.call('lset', KEYS[1], ARGV[1], '__DELETED__');
redis.call('lrem', KEYS[1], 1, '__DELETED__');
LUA;

        return $this->getConnection()->eval($lua, 1, $key, $index);
    }

    public function form()
    {
        $this->form->hidden('conn')->value($this->conn);
        $this->form->hidden('type')->value('list');
        $this->form->text('key');
        $this->form->number('ttl')->default(-1);
        $this->form->list('value')->sortable();
    }
}
