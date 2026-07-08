<?php

require __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (\Exception $e) {
    die("Error: No se pudo cargar el archivo .env");
}

$config = [
    'key' => $_ENV['AWS_ACCESS_KEY_ID_isgcd'] ?? null,
    'secret' => $_ENV['AWS_SECRET_ACCESS_KEY_isgcd'] ?? null,
    'region' => $_ENV['AWS_DEFAULT_REGION_isgcd'] ?? 'us-south',
    'bucket' => $_ENV['AWS_BUCKET_isgcd'] ?? null,
    'endpoint' => $_ENV['AWS_ENDPOINT_isgcd'] ?? null,
];

header('Content-Type: text/plain');
echo "--- LISTADO DE ARCHIVOS: {$config['bucket']} ---\n\n";

try {
    $s3 = new S3Client([
        'version' => 'latest',
        'region' => $config['region'],
        'endpoint' => $config['endpoint'],
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key' => $config['key'],
            'secret' => $config['secret'],
        ],
        'http' => [
            'verify' => false
        ]
    ]);

    $result = $s3->listObjects([
        'Bucket' => $config['bucket'],
    ]);

    if (isset($result['Contents']) && count($result['Contents']) > 0) {
        $total = count($result['Contents']);
        echo "Total de archivos: $total\n\n";

        foreach ($result['Contents'] as $archivo) {
            $nombre = $archivo['Key'];
            $tamano = number_format($archivo['Size'] / 1024, 2);
            $modificado = $archivo['LastModified']->__toString();
            echo "[ARCHIVO] $nombre\n";
            echo "          Tamaño: {$tamano} KB  |  Última modificación: $modificado\n\n";
        }
    } else {
        echo "El bucket está vacío.\n";
    }

} catch (AwsException $e) {
    echo "ERROR DE CONEXIÓN:\n";
    echo "Código: " . $e->getAwsErrorCode() . "\n";
    echo "Mensaje: " . $e->getAwsErrorMessage() . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}