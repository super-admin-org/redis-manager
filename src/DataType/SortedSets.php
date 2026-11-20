<?php

namespace SuperAdmin\Admin\RedisManager\DataType;

use Illuminate\Support\Arr;

/**
 * The editor treats a sorted set as an ordered list: the position of a member is its score.
 */
class SortedSets extends DataType
{
    /**
     * Members with their scores, lowest score first.
     *
     * @return array<string, float>
     */
    public function fetch(string $key)
    {
        if ($this->isPhpRedis()) {
            return (array) $this->getConnection()->client()->zRange($key, 0, -1, true);
        }

        return $this->getConnection()->zrange($key, 0, -1, ['WITHSCORES' => true]);
    }

    public function store(array $params)
    {
        $key = Arr::get($params, 'key');
        $ttl = (int) Arr::get($params, 'ttl', -1);

        foreach ($this->values(Arr::get($params, 'value')) as $position => $member) {
            $this->getConnection()->zadd($key, [$member => $position]);
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

        return $this->getConnection()->zrem($key, $member);
    }

    /**
     * The form edits the members in score order. Members with equal scores are all kept.
     */
    public function prepareData($data)
    {
        $data['value'] = array_map('strval', array_keys((array) $data['value']));

        return $data;
    }

    public function form()
    {
        $this->form->hidden('conn')->value($this->conn);
        $this->form->hidden('type')->value('zset');
        $this->form->text('key');
        $this->form->number('ttl')->default(-1);
        $this->form->list('value')->sortable();
    }
}
