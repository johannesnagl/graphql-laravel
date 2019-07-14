<?php 

use Rebing\GraphQL\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class CacheCommandsTest extends TestCase
{
    public function testCache()
    {
        $this->mock(FileSystem::class, function($mock) {
            $mock->shouldReceive('delete')->with(app('graphql')->getCachedConfigPath())->andReturn(0);
            $mock->shouldReceive('put')->with(app('graphql')->getCachedConfigPath(), \Mockery::any());
        });

        $this->artisan('graphql:cache')
        ->assertExitCode(0);
    }

    public function testClear()
    {
        $this->mock(FileSystem::class, function($mock) {
            $mock->shouldReceive('delete')->with(app('graphql')->getCachedConfigPath())->andReturn(0);
        });

        $this->artisan('graphql:clear')
        ->assertExitCode(0);
    }

}
