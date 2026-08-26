<?php

declare(strict_types=1);

// Endpoint da drop zone — recebe multiplos arquivos e responde JSON.
// Rode:  php -S localhost:8000 -t exemplo  (a partir da pasta do pacote)

use Hermes\UploadMultiple\UploadMultiple;
use Iscos\Voodoo\Voodoo;

require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Voodoo::fromEnv(__DIR__ . '/.env');

    $multi = new UploadMultiple($db, [
        'pasta' => __DIR__ . '/uploads',
        'max_tamanho' => 5 * 1024 * 1024,
        'permitidos' => ['jpg', 'png', 'webp', 'gif'],
        'max_arquivos' => 10,
    ]);

    $resultado = $multi->salvarVarios($_FILES['fotos'] ?? [], 'galeria', [], [
        'webp' => 80,
        'thumbs' => [[300, null], [300, 300]],
    ]);

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['erros' => ['geral' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
}
