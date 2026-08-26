<?php

declare(strict_types=1);

namespace Hermes\UploadMultiple\Tests;

use Hermes\CropImage\ImageRepository as CropImageRepository;
use Hermes\Upload\UploadRepository;
use Hermes\UploadMultiple\UploadMultiple;
use Iscos\Voodoo\Voodoo;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UploadMultipleTest extends TestCase
{
    private string $dir;
    private \Iscos\Voodoo\Database $db;
    private UploadMultiple $multi;
    private UploadRepository $repo;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('ext-gd necessario.');
        }

        $this->dir = sys_get_temp_dir() . '/hermes-multi-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);

        $this->db = Voodoo::open('sqlite:' . $this->dir . '/teste.sqlite');
        $this->repo = new UploadRepository($this->db);
        $this->repo->criaTabela();
        (new CropImageRepository($this->db))->criaTabela();

        $this->multi = new UploadMultiple($this->db, [
            'pasta' => $this->dir . '/uploads',
            'max_tamanho' => 1024 * 1024,
            'permitidos' => ['txt', 'jpg'],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/uploads/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->dir . '/uploads/cache/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir . '/uploads/cache');
        @rmdir($this->dir . '/uploads');
        @unlink($this->dir . '/teste.sqlite');
        @rmdir($this->dir);
    }

    private function arquivoFake(string $nome, string $conteudo): array
    {
        $tmp = $this->dir . '/' . bin2hex(random_bytes(4)) . '-' . $nome;
        file_put_contents($tmp, $conteudo);

        return [
            'name' => $nome,
            'type' => 'text/plain',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($conteudo),
        ];
    }

    private function fotoFake(string $nome = 'foto.jpg'): array
    {
        $tmp = $this->dir . '/' . bin2hex(random_bytes(4)) . '-' . $nome;
        $im = imagecreatetruecolor(200, 150);
        imagefilledrectangle($im, 0, 0, 199, 149, imagecolorallocate($im, 90, 140, 200));
        imagejpeg($im, $tmp, 90);
        imagedestroy($im);

        return [
            'name' => $nome,
            'type' => 'image/jpeg',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];
    }

    /** Monta o formato $_FILES['fotos'] (arrays) a partir de arquivos individuais. */
    private function filesMultiple(array ...$arquivos): array
    {
        return [
            'name' => array_map(fn ($a) => $a['name'], $arquivos),
            'type' => array_map(fn ($a) => $a['type'], $arquivos),
            'tmp_name' => array_map(fn ($a) => $a['tmp_name'], $arquivos),
            'error' => array_map(fn ($a) => $a['error'], $arquivos),
            'size' => array_map(fn ($a) => $a['size'], $arquivos),
        ];
    }

    public function testNormalizaArquivoUnico(): void
    {
        $unico = UploadMultiple::normaliza($this->arquivoFake('a.txt', 'x'));

        $this->assertCount(1, $unico);
        $this->assertSame('a.txt', $unico[0]['name']);
    }

    public function testNormalizaMultiplos(): void
    {
        $lista = UploadMultiple::normaliza(
            $this->filesMultiple($this->arquivoFake('a.txt', 'x'), $this->arquivoFake('b.txt', 'y')),
        );

        $this->assertCount(2, $lista);
        $this->assertSame('b.txt', $lista[1]['name']);
    }

    public function testNormalizaIgnoraCamposVazios(): void
    {
        $lista = UploadMultiple::normaliza([
            'name' => ['', 'b.txt'],
            'tmp_name' => ['', 'tmp-b'],
            'error' => [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
            'size' => [0, 1],
        ]);

        $this->assertCount(1, $lista);
        $this->assertSame('b.txt', $lista[0]['name']);
    }

    public function testSalvarVariosImagens(): void
    {
        $resultado = $this->multi->salvarVarios(
            $this->filesMultiple($this->fotoFake('um.jpg'), $this->fotoFake('dois.jpg')),
            'galeria',
            [],
            ['webp' => 75, 'thumbs' => [[100, 100]]],
        );

        $this->assertSame(2, $resultado['total']);
        $this->assertSame([], $resultado['erros']);
        $this->assertCount(2, $resultado['registros']);
        $this->assertNotNull($resultado['registros'][0]['imagem_id']);
        $this->assertSame(2, $this->repo->contar('galeria'));
    }

    public function testErroParcialContinuaComOsDemais(): void
    {
        $resultado = $this->multi->salvarVarios(
            $this->filesMultiple($this->fotoFake('boa.jpg'), $this->arquivoFake('ruim.php', 'x')),
            'galeria',
        );

        $this->assertSame(1, $resultado['total']);
        $this->assertCount(1, $resultado['registros']);
        $this->assertArrayHasKey('ruim.php', $resultado['erros']);
        $this->assertSame(1, $this->repo->contar('galeria'));
    }

    public function testMaxArquivos(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Máximo/');

        $multi = new UploadMultiple($this->db, [
            'pasta' => $this->dir . '/uploads',
            'max_arquivos' => 1,
        ]);
        $multi->salvarVarios($this->filesMultiple($this->fotoFake('a.jpg'), $this->fotoFake('b.jpg')));
    }

    public function testSemArquivos(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Nenhum arquivo/');

        $this->multi->salvarVarios([]);
    }
}
