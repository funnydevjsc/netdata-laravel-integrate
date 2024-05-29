<?php

namespace FunnyDev\Netdata;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class NetdataSdk
{
    public string $server;
    public string $version;
    public string $scope_node;
    public string $username;
    public string $password;

    public function __construct(string $server='', int $version=0, string $scope_node='', string $username='', string $password='')
    {
        $this->server = $this->getConfigValue($server, 'server');
        $this->version = $this->getConfigValue($version, 'version');
        $this->scope_node = $this->getConfigValue($scope_node, 'scope_node');
        $this->username = $this->getConfigValue($username, 'username');
        $this->password = $this->getConfigValue($password, 'password');
    }

    private function getConfigValue($value, $configKey)
    {
        return $value ? $value : config('netdata.'.$configKey);
    }

    private function get(string $url): array
    {
        $httpClient = Http::withHeaders([
            'Priority' => 'u=1, i',
            'Referer' => $this->server.'/v2/spaces/server/rooms/local/overview',
            'Sec-Ch-Ua' => '"Google Chrome";v="125", "Chromium";v="125", "Not.A/Brand";v="24"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"macOS"',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
        ])
            ->withOptions(['verify' => false])
            ->timeout(10);

        if (!empty($this->username) && !empty($this->password)) {
            $httpClient = $httpClient->withBasicAuth($this->username, $this->password);
        }

        $response = $httpClient->get($url);

        return $response->json();
    }

    public function cpu(int $start_time, int $end_time): array
    {
        $param = [
            'points' => 259,
            'format' => 'json2',
            'time_group' => 'average',
            'time_resampling' => 0,
            'after' => $start_time,
            'before' => $end_time,
            'options' => 'jsonwrap|nonzero|flip|ms|jw-anomaly-rates|minify',
            'contexts' => '*',
            'scope_contexts' => 'system.cpu_some_pressure',
            'scope_nodes' => $this->scope_node,
            'nodes' => '*',
            'instances' => '*',
            'dimensions' => '*',
            'labels' => '*',
            'group_by[0]' => 'dimension',
            'group_by_label[0]' => '',
            'aggregation[0]' => 'avg'
        ];
        $url = $this->server.'/api/v'.$this->version.'/data?' . http_build_query($param);
        $response = $this->get($url);
        $cpu_usage = [
            'time' => [],
            '10' => [
                'avg' => isset($response['summary']['dimensions'][0]['sts']['avg']) ? round($response['summary']['dimensions'][0]['sts']['avg'], 2) : 0,
                'data' => []
            ],
            '1m' => [
                'avg' => isset($response['summary']['dimensions'][1]['sts']['avg']) ? round($response['summary']['dimensions'][1]['sts']['avg'], 2) : 0,
                'data' => []
            ],
            '5m' => [
                'avg' => isset($response['summary']['dimensions'][2]['sts']['avg']) ? round($response['summary']['dimensions'][2]['sts']['avg'], 2) : 0,
                'data' => []
            ]
        ];
        foreach ($response['result']['data'] as $data) {
            $cpu_usage['time'][] = date('H:i:s', $data[0] / 1000);
            $cpu_usage['10']['data'][] = round($data[1][0], 2);
            $cpu_usage['1m']['data'][] = round($data[2][0], 2);
            $cpu_usage['5m']['data'][] = round($data[3][0], 2);
        }
        $cpu_usage['10']['data'] = array_slice($cpu_usage['10']['data'], -10, 10);
        $cpu_usage['1m']['data'] = array_slice($cpu_usage['1m']['data'], -60, 60);
        return $cpu_usage;
    }

    public function ram(int $start_time, int $end_time): array
    {
        $param = [
            'points' => 259,
            'format' => 'json2',
            'time_group' => 'average',
            'time_resampling' => 0,
            'after' => $start_time,
            'before' => $end_time,
            'options' => 'jsonwrap|nonzero|flip|ms|jw-anomaly-rates|minify',
            'contexts' => '*',
            'scope_contexts' => 'system.ram',
            'scope_nodes' => $this->scope_node,
            'nodes' => '*',
            'instances' => '*',
            'dimensions' => '*',
            'labels' => '*',
            'group_by[0]' => 'dimension',
            'group_by_label[0]' => '',
            'aggregation[0]' => 'avg'
        ];
        $url = $this->server.'/api/v'.$this->version.'/data?' . http_build_query($param);
        $response = $this->get($url);
        $ram_usage = [
            'avg' => round(floatval($response['summary']['dimensions'][1]['sts']['con']), 2),
            '10' => [
                'time' => [],
                'buffer' => [],
                'cached' => [],
                'used' => []
            ],
            '1m' => [
                'time' => [],
                'buffer' => [],
                'cached' => [],
                'used' => []
            ],
            '5m' => [
                'time' => [],
                'buffer' => [],
                'cached' => [],
                'used' => []
            ]
        ];
        $division_time = [];
        $buffer = [];
        $cached = [];
        $used = [];
        foreach ($response['result']['data'] as $data) {
            $division_time[] = date('H:i:s', $data[0] / 1000);
            $buffer[] = round(floatval($data[4][0]) * 1.048576 / 1000, 2);
            $cached[] = round(floatval($data[3][0]) * 1.048576 / 1000, 2);
            $used[] = round(floatval($data[2][0]) * 1.048576 / 1000, 2);
        }

        $ram_usage['10']['time'] = array_slice($division_time, -10, 10);
        $ram_usage['1m']['time'] = array_slice($division_time, -60, 60);
        $ram_usage['5m']['time'] = $division_time;

        $ram_usage['10']['buffer'] = array_slice($buffer, -10, 10);
        $ram_usage['1m']['buffer'] = array_slice($buffer, -60, 60);
        $ram_usage['5m']['buffer'] = $buffer;

        $ram_usage['10']['cached'] = array_slice($cached, -10, 10);
        $ram_usage['1m']['cached'] = array_slice($cached, -60, 60);
        $ram_usage['5m']['cached'] = $cached;

        $ram_usage['10']['used'] = array_slice($used, -10, 10);
        $ram_usage['1m']['used'] = array_slice($used, -60, 60);
        $ram_usage['5m']['used'] = $used;
        return $ram_usage;
    }

    public function disk(int $start_time, int $end_time): array
    {
        $param = [
            'points' => 259,
            'format' => 'json2',
            'time_group' => 'average',
            'time_resampling' => 0,
            'after' => $start_time,
            'before' => $end_time,
            'options' => 'jsonwrap|nonzero|flip|ms|jw-anomaly-rates|minify',
            'contexts' => '*',
            'scope_contexts' => 'disk.io',
            'scope_nodes' => $this->scope_node,
            'nodes' => '*',
            'instances' => '*',
            'dimensions' => '*',
            'labels' => '*',
            'group_by[0]' => 'dimension',
            'group_by_label[0]' => '',
            'aggregation[0]' => 'avg'
        ];
        $url = $this->server.'/api/v'.$this->version.'/data?' . http_build_query($param);
        $response = $this->get($url);
        $disk_usage = [
            'avg' => [
                'read' => round(floatval($response['summary']['dimensions'][0]['sts']['avg']) * 1.048576, 2),
                'write' => round(floatval($response['summary']['dimensions'][1]['sts']['avg']) * 1.048576, 2)
            ],
            '10' => [
                'time' => [],
                'read' => [],
                'write' => [],
                'min' => -20,
                'max' => 20
            ],
            '1m' => [
                'time' => [],
                'read' => [],
                'write' => [],
                'min' => -20,
                'max' => 20
            ],
            '5m' => [
                'time' => [],
                'read' => [],
                'write' => [],
                'min' => -20,
                'max' => 20
            ]
        ];
        $division_time = [];
        $read = [];
        $write = [];
        foreach ($response['result']['data'] as $data) {
            $division_time[] = date('H:i:s', $data[0] / 1000);
            $read[] = round(floatval($data[1][0]) * 1.048576, 2);
            $write[] = round(floatval($data[2][0]) * 1.048576, 2);
        }

        $disk_usage['10']['time'] = array_slice($division_time, -10, 10);
        $disk_usage['1m']['time'] = array_slice($division_time, -60, 60);
        $disk_usage['5m']['time'] = $division_time;

        $disk_usage['10']['read'] = array_slice($read, -10, 10);
        $disk_usage['1m']['read'] = array_slice($read, -60, 60);
        $disk_usage['5m']['read'] = $read;

        $disk_usage['10']['write'] = array_slice($write, -10, 10);
        $disk_usage['1m']['write'] = array_slice($write, -60, 60);
        $disk_usage['5m']['write'] = $write;

        $disk_usage['10']['min'] = round(min([min($disk_usage['10']['read']), min($disk_usage['10']['write'])]));
        $disk_usage['10']['max'] = round(max([max($disk_usage['10']['read']), max($disk_usage['10']['write'])]));
        $disk_usage['1m']['min'] = round(min([min($disk_usage['1m']['read']), min($disk_usage['1m']['write'])]));
        $disk_usage['1m']['max'] = round(max([max($disk_usage['1m']['read']), max($disk_usage['1m']['write'])]));
        $disk_usage['5m']['min'] = round(min([min($disk_usage['5m']['read']), min($disk_usage['5m']['write'])]));
        $disk_usage['5m']['max'] = round(max([max($disk_usage['5m']['read']), max($disk_usage['5m']['write'])]));


        return $disk_usage;
    }

    public function network(int $start_time, int $end_time): array
    {
        $param = [
            'points' => 259,
            'format' => 'json2',
            'time_group' => 'average',
            'time_resampling' => 0,
            'after' => $start_time,
            'before' => $end_time,
            'options' => 'jsonwrap|nonzero|flip|ms|jw-anomaly-rates|minify',
            'contexts' => '*',
            'scope_contexts' => 'system.net',
            'scope_nodes' => $this->scope_node,
            'nodes' => '*',
            'instances' => '*',
            'dimensions' => '*',
            'labels' => '*',
            'group_by[0]' => 'dimension',
            'group_by_label[0]' => '',
            'aggregation[0]' => 'sum'
        ];
        $url = $this->server.'/api/v'.$this->version.'/data?' . http_build_query($param);
        $response = $this->get(url: $url);

        $net_usage = [
            'avg' => [
                'received' => round(floatval($response['summary']['dimensions'][0]['sts']['avg']) * 0.125, 2),
                'sent' => round(floatval($response['summary']['dimensions'][1]['sts']['avg']) * 0.125, 2)
            ],
            '10' => [
                'time' => [],
                'received' => [],
                'sent' => [],
                'min' => -100,
                'max' => 100
            ],
            '1m' => [
                'time' => [],
                'received' => [],
                'sent' => [],
                'min' => -100,
                'max' => 100
            ],
            '5m' => [
                'time' => [],
                'received' => [],
                'sent' => [],
                'min' => -100,
                'max' => 100
            ]
        ];
        $division_time = [];
        $received = [];
        $send = [];
        foreach ($response['result']['data'] as $data) {
            $division_time[] = date('H:i:s', $data[0] / 1000);
            $received[] = round(floatval($data[1][0]) * 0.125, 2);
            $send[] = round(floatval($data[2][0]) * 0.125, 2);
        }

        $net_usage['10']['time'] = array_slice($division_time, -10, 10);
        $net_usage['1m']['time'] = array_slice($division_time, -60, 60);
        $net_usage['5m']['time'] = $division_time;

        $net_usage['10']['received'] = array_slice($received, -10, 10);
        $net_usage['1m']['received'] = array_slice($received, -60, 60);
        $net_usage['5m']['received'] = $received;

        $net_usage['10']['sent'] = array_slice($send, -10, 10);
        $net_usage['1m']['sent'] = array_slice($send, -60, 60);
        $net_usage['5m']['sent'] = $send;

        $net_usage['10']['min'] = round(min([min($net_usage['10']['received']), min($net_usage['10']['sent'])]));
        $net_usage['10']['max'] = round(max([max($net_usage['10']['received']), max($net_usage['10']['sent'])]));
        $net_usage['1m']['min'] = round(min([min($net_usage['1m']['received']), min($net_usage['1m']['sent'])]));
        $net_usage['1m']['max'] = round(max([max($net_usage['1m']['received']), max($net_usage['1m']['sent'])]));
        $net_usage['5m']['min'] = round(min([min($net_usage['5m']['received']), min($net_usage['5m']['sent'])]));
        $net_usage['5m']['max'] = round(max([max($net_usage['5m']['received']), max($net_usage['5m']['sent'])]));


        return $net_usage;
    }

}
