<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Rebing\GraphQL\GraphQL;
use Illuminate\Contracts\Cache\Repository;

class CacheCommand extends Command
{
    protected $signature = 'graphql:cache';

    protected $description = 'Caches graphQL types mapping for faster configuration loading';

    protected $files;

    protected $graphql;

    protected const CACHE_KEY = 'graphql:cache';

    public function __construct(Filesystem $files, GraphQL $graphql, Repository $cache)
    {
        parent::__construct();

        $this->files = $files;
        $this->graphql = $graphql;
        $this->cache = $cache;
    }

    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws \LogicException
     */
    public function handle()
    {
        $this->call('graphql:clear');

        $this->cache->put(self::CACHE_KEY, $this->graphql);

        $this->info('Configuration cached successfully!');
    }
}
