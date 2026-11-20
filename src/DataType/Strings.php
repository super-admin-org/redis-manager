<?php

namespace SuperAdmin\Admin\RedisManager\DataType;

use Illuminate\Support\Arr;

class Strings extends DataType
{
    public function fetch(string $key)
    {
        return $this->getConnection()->get($key);
    }

    public function update(array $params)
    {
        return $this->store($params);
    }

    public function store(array $params)
    {
        $key = Arr::get($params, 'key');
        $value = (string) Arr::get($params, 'value', '');
        $ttl = (int) Arr::get($params, 'ttl', -1);

        $this->getConnection()->set($key, $value);

        if ($ttl > 0) {
            $this->getConnection()->expire($key, $ttl);
        }

        return redirect(route('redis-index', [
            'conn' => $this->conn,
        ]));
    }

    public function form()
    {
        $this->form->hidden('conn')->value($this->conn);
        $this->form->hidden('type')->value('string');
        $this->form->text('key');
        $this->form->textarea('value')->rows(4);
        $this->form->number('ttl')->default(-1);
    }
}
