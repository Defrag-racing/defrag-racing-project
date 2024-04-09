<?php

namespace App\External;

use Illuminate\Support\Facades\Process;

class DemoValidator {
    private $data = [];

    public function __construct($filename) {
        $result = Process::run(base_path() . '/storage/app/tools/UDT_json -c ' . storage_path('app/' . $filename));

        if ($result->failed()) {
            throw new \Exception("Couldn't validate demo, if you are confident about the demo file, please contact the admins.");
        }

        $json = json_decode($result->output());

        if ($json == null) {
            throw new \Exception("Couldn't validate demo, if you are confident about the demo file, please contact the admins.");
        }

        $this->data = $json;
    }

    public function getRawData() {
        return $this->data;
    }

    public function get_physics() {
        $promode = $this->data->gameStates[0]->configStringValues->df_promode;

        if ($promode == '1') {
            return 'cpm';
        } else {
            return 'vq3';
        }
    }

    public function parse_time($demo_time_cmd) {
        $demo_time_cmd = preg_replace("/(\\^.|\\\"|\\n|\")/", "", $demo_time_cmd);

        $demo_time_cmd = substr($demo_time_cmd, strpos($demo_time_cmd, ':') + 2);

        $sp_ind = strpos($demo_time_cmd, ' ');

        if ($sp_ind > 0) {
            $demo_time_cmd = substr($demo_time_cmd, 0, $sp_ind);
        }

        return $this->get_time_span($demo_time_cmd);
    }

    public function parse_time_online($demo_time_cmd) {
        $demo_time_cmd = preg_replace("/(\\^[0-9]|\\\"|\\n|\")/", "", $demo_time_cmd);
        $demo_time = substr($demo_time_cmd, strrpos($demo_time_cmd, "in") + 3);

        $est_index = strpos($demo_time, " (est");

        if ($est_index > 0) {
            $demo_time = substr($demo_time, 0, $est_index);
        }

        return $this->get_time_span($demo_time);
    }

    public function get_time() {
        $rawCommands = $this->data->rawCommands;

        foreach ($rawCommands as $command) {
            if (strpos($command->rawCommand, 'print "Time performed by') === 0) {
                return $this->parse_time($command->rawCommand);
            } elseif (strpos($command->rawCommand, 'print "Time performed by') === 0) {
                return $this->parse_time($command->rawCommand);
            } elseif (strpos($command->rawCommand, 'print "^3Time Performed') === 0) {
                return $this->parse_time($command->rawCommand);
            } elseif (strpos($command->rawCommand, 'print "') === 0 && strpos($command->rawCommand, 'reached the finish line in') !== false) {
                return $this->parse_time_online($command->rawCommand);
            } elseif (strpos($command->rawCommand, 'print "') === 0 && strpos($command->rawCommand, 'reached the finish line in') !== false) {
                return $this->parse_time_online($command->rawCommand);
            }
        }

        throw new \Exception("Unable to validate demo . CODE DT-P-1");
    }

    public function get_time_span($time_str) {
        $data = explode(':', $time_str);

        if (count($data) == 4) {
            $hours = (int)$data[0];
            $minutes = (int)$data[1];
            $seconds = (int)$data[2];
            $milliseconds = (int)$data[3];
        } elseif (count($data) == 3) {
            $hours = 0;
            $minutes = (int)$data[0];
            $seconds = (int)$data[1];
            $milliseconds = (int)$data[2];
        } elseif (count($data) == 2) {
            $hours = 0;
            $minutes = 0;
            $seconds = (int)$data[0];
            $milliseconds = (int)$data[1];
        } else {
            $hours = 0;
            $minutes = 0;
            $seconds = 0;
            $milliseconds = (int)$data[0];
        }

        $total_milliseconds = $milliseconds + $seconds * 1000 + $minutes * 60 * 1000 + $hours * 60 * 60 * 1000;

        return $total_milliseconds;
    }

    public function validate_maps($round) {
        $config = $this->data->gameStates[0]->configStringValues;

        $roundmaps = $round->maps()->get();

        if (count($roundmaps) == 0) {
            throw new \Exception('No maps are added to this round.');
        }

        foreach ($roundmaps as $roundmap) {
            if ($roundmap->crc !== '') {
                if ($config->defrag_bspcrc != (string)$roundmap->crc) {
                    throw new \Exception('Map is not valid. Must be one of the maps added to this round.');
                }
            }

            if (strtolower($config->mapname) == strtolower($roundmap->name)) {
                return;
            }
        }

        throw new \Exception('Map is not valid. Must be one of the maps added to this round.');
    }

    public function validate() {
        $config = $this->data->gameStates[0]->configStringValues;

        foreach($config as $key => $value) {
            $config->{strtolower($key)} = $value;
        }

        $rules = [
            'df_mp_interferenceoff' => '3',
            'g_speed' => '320',
            'sv_cheats' => '0',
            'timescale' => '1',
            'g_gravity' => '800',
            'g_knockback' => '1000',
            'pmove_fixed' => '1'
        ];

        foreach ($rules as $rule => $value) {
            if (!isset($config->$rule) || strtolower((string)$config->$rule) != (string)$value) {
                throw new \Exception('Rule ' . $rule . ' is not valid. Must be ' . $value . '.');
            }
        }
    }

    public function get_data() {
        $result = [];

        $result['time'] = $this->get_time();
        $result['physics'] = $this->get_physics();

        return $result;
    }
}
?>
