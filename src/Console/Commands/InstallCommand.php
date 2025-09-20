<?php

namespace Miguilim\LaravelStronghold\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stronghold:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Laravel Stronghold package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing Laravel Stronghold...');

        $this->info('Publishing configuration...');
        $this->call('vendor:publish', ['--tag' => 'stronghold-config']);

        $this->info('Publishing migrations...');
        $this->call('vendor:publish', ['--tag' => 'stronghold-migrations']);

        $this->info('Publishing stubs...');
        $this->call('vendor:publish', ['--tag' => 'stronghold-stubs']);

        $this->info('Laravel Stronghold installed successfully.');

        return Command::SUCCESS;
    }
}