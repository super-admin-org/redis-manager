<?php

namespace SuperAdmin\Admin\RedisManager\DataType;

use Illuminate\Support\Arr;

class Sets extends DataType
{
    public function fetch(string $key)
    {
        return $this->getConnection()->smembers($key);
    }

    public function store(array $params)
    {
        $key = Arr::get($params, 'key');
        $ttl = (int) Arr::get($params, 'ttl', -1);
        $members = $this->values(Arr::get($params, 'value'));

        if ($members) {
            $this->getConnection()->sadd($key, ...$members);
        }

        if ($ttl > 0) {
            $this->getConnection()->expire($key, $ttl);
        }

        return $this->editRedirect($key);
    }

    public function remove(array $params)
    {
        $key = Arr::get($params, 'key');
        $member = (string) Arr::get($params, 'member');

        return $this->getConnection()->srem($key, $member);
    }

    public function form()
    {
        $this->form->hidden('conn')->value($this->conn);
        $this->form->hidden('type')->value('set');
        $this->form->text('key');
        $this->form->number('ttl')->default(-1);
        $this->form->list('value');
    }
}
