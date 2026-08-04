<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class SimpleQueue
{
    public static function push($queueName, $item)
    {
        $queue = Cache::get($queueName, []);
        $queue[] = $item;
        Cache::put($queueName, array_values(array_unique($queue)), now()->addHours(24));
    }

    public static function pop($queueName)
    {
        $queue = Cache::get($queueName, []);
        if (empty($queue)) return null;
        $item = array_shift($queue);
        Cache::put($queueName, array_values($queue), now()->addHours(24));
        return $item;
    }

    public static function remove($queueName, $item)
    {
        $queue = Cache::get($queueName, []);
        $queue = array_filter($queue, function ($i) use ($item) {
            return $i !== $item;
        });
        Cache::put($queueName, array_values($queue), now()->addHours(24));
    }
}

