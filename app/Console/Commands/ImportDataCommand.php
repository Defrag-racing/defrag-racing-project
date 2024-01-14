<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Server;
use App\Models\Map;

class ImportDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import {function} {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports Json data to database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $func = $this->argument('function');
        $file = 'externaldata/' . $this->argument('file');

        // Your command logic here
        $this->info("Importing data using: $func");

        if (! $this->confirm('Do you want to continue?')) {
            $this->error("Importing aborted !!!");

            
        }
        
        $this->info("Importing Data using: $func");
        $data = $this->get_json_data($file);

        if ($data === null) {
            $this->error('Invalid Json Data in: ' . $file);
            return;
        }

        $this->$func($data);
    }

    public function get_json_data($file) {
        try {
            $jsonData = file_get_contents($file);

            $data = json_decode($jsonData, true);

            return $data;
        } catch(\Exception $e) {
            $this->error($e->getMessage());
            return null;
        }
    }

    public function servers($data) {
        foreach($data as $element) {
            $newElement = array_merge([], $element);

            $map = json_decode($element['map'])->name;
            $defrag = json_decode($element['state'])->defrag;

            $newElement['map'] = $map;
            $newElement['defrag'] = $defrag;

            $server = new Server($newElement);
            $server->save();
        }
    }

    public function maps($data) {
        foreach($data as $element) {
            $newElement = array_merge([], $element);

            $newElement['author'] = $element['author_name'];
            $newElement['pk3'] = $element['pk3_file'];
            $newElement['date_added'] = $element['date_added_ws'];
            $newElement['visible'] = $element['is_visible'];

            $map = new Map($newElement);
            $map->save();
        }
    }
}
