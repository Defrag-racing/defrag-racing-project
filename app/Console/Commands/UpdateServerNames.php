<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Server;

class UpdateServerNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:servers:names';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update plain names of servers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $servers = Server::all();

        foreach($servers as $server) {
            $server->plain_name = $this->getPlainName($server->name);
            $server->save();
        }
    }

    public function getPlainName($name) {
        $pattern = '/\^\w/';
        $plainName = preg_replace($pattern, '', trim($name));

        return trim($plainName);
    }
}
