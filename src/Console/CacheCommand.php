<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Rebing\GraphQL\GraphQL;
use Illuminate\Support\Facades\Storage;

class CacheCommand extends Command
{
    protected $signature = 'graphql:cache';

    protected $description = 'Caches graphQL types mapping for faster configuration loading';

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
        $this->call('graphql:clear');

        $configPath = $this->graphql->getCachedConfigPath();

        $this->files->put($configPath, '<?php return '.var_export(config('graphql.types'), true).';'.PHP_EOL);

        try {
            require $configPath;
        } catch (Throwable $e) {
            $this->files->delete($configPath);

            throw new LogicException('Your configuration files are not serializable.', 0, $e);
        }

        $this->info('Configuration cached successfully!');
    }

}
