<?php

declare(strict_types=1);

namespace Rebing\GraphQL\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CacheClearCommand extends Command
{
    protected $signature = 'graphql:clear';

    protected $description = 'Remove the configuration cache file';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new config cache command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
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

        $config = $this->getFreshConfiguration();

        $configPath = $this->getCachedFilePath();

        $this->files->put(
            $configPath, '<?php return '.var_export($config, true).';'.PHP_EOL
        );

        try {
            require $configPath;
        } catch (Throwable $e) {
            $this->files->delete($configPath);

            throw new LogicException('Your configuration files are not serializable.', 0, $e);
        }

        $this->info('Configuration cached successfully!');
    }

    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function getCachedFilePath()
    {
        return $_ENV['APP_GRAPHQL_CACHE'] ?? $this->bootstrapPath().'/cache/graphql.php';
    }

}
