# UploadMultiple — multi-upload com drop zone

> Parte da família **hermes_\*** — ferramentas completas, interligadas e com
> estrutura de banco para quem programa PHP na unha.
> Criado e mantido por **Hermes Agent (Nous Research)** · publicado por Ildefonso Costa.

Multi-upload para PHP sem framework: **N arquivos de uma vez**, cada um validado
(hermes/validators), imagens processadas (hermes/crop-image: WebP, thumbs, marca
d'água proporcional) e tudo registrado no banco (hermes_uploads). Acompanha uma
**drop zone em JS puro** — arrastar e soltar com preview, zero dependências no front.

## Instalação

```bash
composer require hermes/upload-multiple
```

## Uso

```php
use Hermes\UploadMultiple\UploadMultiple;
use Iscos\Voodoo\Voodoo;

$db = Voodoo::fromEnv(); // .env formato Laravel

$multi = new UploadMultiple($db, [
    'pasta'       => 'uploads',
    'max_tamanho' => 2 * 1024 * 1024,
    'permitidos'  => ['jpg', 'png', 'webp'],
    'max_arquivos'=> 10,
]);

// $_FILES['fotos'] com name[], tmp_name[], ... (form: <input type="file" multiple name="fotos[]">)
$resultado = $multi->salvarVarios($_FILES['fotos'], 'galeria', $_POST, [
    'webp'   => 80,
    'thumbs' => [[300, null], [300, 300]],
]);

echo $resultado['total'];                      // quantos foram enviados
foreach ($resultado['registros'] as $r) { }    // cada upload (com id e imagem_id)
print_r($resultado['erros']);                  // ['arquivo.jpg' => 'motivo', ...]
```

**Erro parcial não derruba o lote**: um arquivo inválido vira entrada em `erros`,
os demais seguem e são registrados.

## Drop zone (exemplo incluído)

A pasta `exemplo/` tem a drop zone completa em **JS puro** (zero bibliotecas):

```
php -S localhost:8000 -t vendor/hermes/upload-multiple/exemplo
```

- Arraste imagens para a área tracejada (destaque ao passar por cima) ou clique
- Previews com `URL.createObjectURL` (nada é enviado antes do submit)
- Envio via `FormData` + `fetch` → JSON com os registros e as miniaturas

## Família hermes_*

| Pacote | Status |
|---|---|
| hermes/validators | ✅ v1.0.1 |
| hermes/crop-image | ✅ v1.0.0 |
| hermes/upload | ✅ v1.0.0 |
| **hermes/upload-multiple** | ✅ **v1.0.0 — este** |
| hermes/gallery | em desenvolvimento (galerias 1:N — usa este + crop-image) |

## Licença

MIT © 2026 Hermes Agent (Nous Research) — criador e mantenedor · Ildefonso Costa — publicador
