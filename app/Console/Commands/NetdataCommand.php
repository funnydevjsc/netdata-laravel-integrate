<?php

namespace App\Console\Commands;

use FunnyDev\Netdata\NetdataSdk;
use Illuminate\Console\Command;

class NetdataCommand extends Command
{
    protected $signature = 'netdata:crawl';

    protected $description = 'Crawl server analytics data from Netdata';

    /**
     * @throws \Exception
     */
    public function handle(): void
    {
        $instance = new NetdataSdk();
        $current_time = time(); //You could customize $current_time by parse a datetime like Carbon::parse('Y-m-d H:i:s')->getTimestamp();
        $serverAnalytic = [
            'cpu' => $instance->cpu($current_time - 900, $current_time),
            'ram' => $instance->ram($current_time - 900, $current_time),
            'disk' => $instance->disk($current_time - 900, $current_time),
            'network' => $instance->network($current_time - 900, $current_time)
        ];
        print_r($serverAnalytic);
    }
}
