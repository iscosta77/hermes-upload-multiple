<?php

declare(strict_types=1);

namespace Hermes\UploadMultiple;

use Hermes\Upload\Upload;
use Iscos\Voodoo\Database;
use RuntimeException;

/**
 * UploadMultiple — multi-upload para PHP sem framework.
 *
 * Estende o hermes/upload para N arquivos de uma vez (o formato de
 * $_FILES['campo'] com arrays: name[], tmp_name[], ...), validando cada
 * arquivo individualmente, processando imagens e gravando tudo no banco.
 *
 * ```php
 * $multi = new UploadMultiple($db, [
 *     'pasta' => 'uploads', 'max_tamanho' => 2 * 1024 * 1024,
 *     'permitidos' => ['jpg', 'png', 'webp'], 'max_arquivos' => 10,
 * ]);
 * $resultado = $multi->salvarVarios($_FILES['fotos'], 'galeria', [], [
 *     'webp' => 80, 'thumbs' => [[400, null]],
 * ]);
 * // ['registros' => [...], 'erros' => ['foto2.jpg' => '...'], 'total' => 3]
 * ```
 *
 * Acompanha a drop zone em JS puro (exemplo/): arrastar e soltar imagens
 * com preview — zero dependencias no front.
 */
final class UploadMultiple
{
    private Database $db;
    private Upload $upload;

    /** @var array{pasta: string, max_tamanho: int, permitidos: array<int, string>, max_arquivos: ?int} */
    private array $opcoes;

    /**
     * @param array{ pasta?: string, max_tamanho?: int, permitidos?: array<int, string>,
     *               regras?: array, max_arquivos?: ?int } $opcoes
     */
    public function __construct(Database $db, array $opcoes = [])
    {
        $this->db = $db;
        $this->opcoes = array_merge(['max_arquivos' => null], $opcoes);
        $this->upload = new Upload($db, $opcoes);
    }

    /**
     * Valida e envia varios arquivos de uma vez.
     *
     * @param array $arquivos $_FILES['campo'] (com name/tmp_name/... em arrays)
     * @param array<string, mixed> $dadosForm campos extras validados
     * @param array $processamento opcoes do hermes/crop-image
     *
     * @return array{ registros: array<int, array<string, mixed>>, erros: array<string, string>, total: int }
     */
    public function salvarVarios(array $arquivos, string $tipo = 'arquivo', array $dadosForm = [], array $processamento = []): array
    {
        $lista = self::normaliza($arquivos);

        if ($lista === []) {
            throw new RuntimeException('Nenhum arquivo foi enviado.');
        }

        $max = $this->opcoes['max_arquivos'];
        if ($max !== null && count($lista) > $max) {
            throw new RuntimeException("Máximo de {$max} arquivo(s) por envio.");
        }

        $registros = [];
        $erros = [];
        foreach ($lista as $arquivo) {
            try {
                $registros[] = $this->upload->salvar($arquivo, $tipo, $dadosForm, $processamento);
            } catch (RuntimeException $e) {
                $erros[(string) ($arquivo['name'] ?? 'arquivo')] = $e->getMessage();
            }
        }

        return [
            'registros' => $registros,
            'erros' => $erros,
            'total' => count($registros),
        ];
    }

    /**
     * Normaliza $_FILES['campo'] para uma lista de arquivos individuais.
     * Aceita tanto a forma de arquivo unico quanto a de multiplos.
     *
     * @return array<int, array{name: string, type: ?string, tmp_name: string, error: int, size: int}>
     */
    public static function normaliza(array $arquivos): array
    {
        // arquivo unico (name e string)
        if (isset($arquivos['name']) && is_string($arquivos['name'])) {
            return [[
                'name' => $arquivos['name'],
                'type' => $arquivos['type'] ?? null,
                'tmp_name' => (string) ($arquivos['tmp_name'] ?? ''),
                'error' => (int) ($arquivos['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($arquivos['size'] ?? 0),
            ]];
        }

        // multiplos: name[], tmp_name[], ...
        $lista = [];
        foreach ((array) ($arquivos['name'] ?? []) as $i => $nome) {
            if ($nome === '') {
                continue; // campo vazio do HTML
            }
            $lista[] = [
                'name' => (string) $nome,
                'type' => $arquivos['type'][$i] ?? null,
                'tmp_name' => (string) ($arquivos['tmp_name'][$i] ?? ''),
                'error' => (int) ($arquivos['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($arquivos['size'][$i] ?? 0),
            ];
        }

        return $lista;
    }
}
