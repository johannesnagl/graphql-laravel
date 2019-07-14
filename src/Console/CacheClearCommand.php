<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Rebing\GraphQL\GraphQL;

class CacheClearCommand extends Command
{
    protected $signature = 'graphql:clear';

    protected $description = 'Remove the configuration cache file';

    protected $files;

    protected $graphql;

    public function __construct(Filesystem $files, GraphQL $graphql)
    {
        parent::__construct();

        $this->files = $files;
        $this->graphql = $graphql;
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
         $this->files->delete($this->graphql->getCachedConfigPath());

        $this->info('Configuration cache cleared!');
    }

}
