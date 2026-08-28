<?php
$json = file_get_contents('d:\Primamas\Ceisa4\prod\openapitps_openapi (1).json');
$data = json_decode($json, true);

$endpoints = [];
foreach ($data['paths'] as $path => $methods) {
    if (isset($methods['get'])) {
        $get = $methods['get'];
        $params = [];
        if (isset($get['parameters'])) {
            foreach ($get['parameters'] as $p) {
                $params[] = $p['name'] . ($p['required'] ? ' (req)' : '');
            }
        }
        $endpoints[] = [
            'path' => $path,
            'summary' => $get['summary'] ?? '',
            'params' => $params
        ];
    }
}

foreach ($endpoints as $ep) {
    echo $ep['path'] . "\n";
    echo "  Summary: " . $ep['summary'] . "\n";
    echo "  Params: " . implode(', ', $ep['params']) . "\n\n";
}
