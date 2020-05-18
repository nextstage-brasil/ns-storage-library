<?php

use NsStorageLibrary\Util;

namespace NsStorageLibrary;

class Config {

    private function __construct() {
        
    }

    public static function init() {
        $nsStorageConfig = [];
        // importar arquivo de configuração desta aplicação.
        $t = explode(DIRECTORY_SEPARATOR, __DIR__);
        for ($i = 0; $i < 4; $i++) {
            array_pop($t);
        }
        $path = implode(DIRECTORY_SEPARATOR, $t);
        $dir = $path . DIRECTORY_SEPARATOR . 'nsStorageConfig.php';
        if (!file_exists($dir)) {
            file_put_contents($dir, file_get_contents(__DIR__ . '/nsStorageConfig.php'));
            die('NsStorageLibrary: Necessário criar/configurar o arquivo "nsStorageConfig.php". Deve estar na mesma pasta onde se encontra o composer.json');
        }

        include $dir;

        ////Não é necessário alterações daqui em diante
        $nsStorageConfig['path_tmp'] = $nsStorageConfig['path'] . DIRECTORY_SEPARATOR . $nsStorageConfig['dir_to_write']; // diretorio absoluto com permissao de escrita
        $nsStorageConfig['path_uploadfile'] = $nsStorageConfig['path_tmp'] . DIRECTORY_SEPARATOR. 'files';
        $nsStorageConfig['path_downloads'] = $nsStorageConfig['path_tmp'] . DIRECTORY_SEPARATOR . 'files'.DIRECTORY_SEPARATOR.'d'; // path a partir da raiz para visualização de arquivos
        $nsStorageConfig['url_downloads'] = $nsStorageConfig['url'] . '/' . $nsStorageConfig['dir_to_write'] . '/files/d';

        $nsStorageConfig['Filerun'] += [
            'scope' => ['profile', 'list', 'upload', 'download', 'weblink', 'delete', 'share', 'admin', 'modify', 'metadata'],
            'bucket' => $nsBucketName,
            'bucket-public' => $nsBucketPublic,
            'path_write' => $nsStorageConfig['path_tmp'] . '/.filerun' // path com permissao de escrita e nao apagavel para registros da classe
        ];
        $nsStorageConfig['S3'] += [
            'version' => '2006-03-01', //nao alterar
            'bucket' => $nsBucketName,
            'bucket-public' => $nsBucketNamePublic,
            'linkPublic' => "https://" . $nsBucketNamePublic . ".s3-" . $nsStorageConfig['S3']['region'] . ".amazonaws.com"
            . ((strlen($nsStorageConfig['pathPrefix'])) ? '/' . $nsStorageConfig['pathPrefix'] : '')
        ];
        $nsStorageConfig['GCP'] += [
            'bucket' => $nsBucketName,
            'bucket-public' => $nsBucketNamePublic,
            'linkPublic' => "https://storage.googleapis.com/$nsBucketNamePublic"
            . ((strlen($nsStorageConfig['pathPrefix'])) ? '/' . $nsStorageConfig['pathPrefix'] : '')
        ];
        $nsStorageConfig['Local'] = [
            'diretorio_local' => $nsStorageConfig['path'] . DIRECTORY_SEPARATOR . $nsStorageConfig['Local']['dir'] . DIRECTORY_SEPARATOR . $nsBucketName, // em relação a raiz da aplicação
            'bucket-public' => $nsStorageConfig['path'] . '/' . $nsStorageConfig['Local']['dir'] . '/' . $nsBucketNamePublic,
            'linkPublic' => $nsStorageConfig['url'] . '/' . $nsStorageConfig['Local']['dir'] . '/' . $nsBucketNamePublic,
            'limiteSizeToOcr' => (1024 * 1024), //1 MB. acima disso, o consumo de memoria nao compensa
        ];

        // Criação de diretório obrigatórios
        Util::mkdir($nsStorageConfig['path_downloads']);
        Util::mkdir($nsStorageConfig['path_tmp']);
        Util::mkdir($nsStorageConfig['Local']['diretorio_local']);
        

        return $nsStorageConfig;
    }

}
