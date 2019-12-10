<?php

namespace NsStorageLibrary\Storage;

use Aws\S3\S3Client;
use DateTime;
use DateTimeZone;
use Google\Cloud\Storage\StorageClient;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Config as Config2;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use NsStorageLibrary\Storage\Adapter\FilerunAdapter;
use NsStorageLibrary\Config;
use Superbalist\Flysystem\GoogleStorage\GoogleStorageAdapter;

//use function Google\Cloud\Core\Lock\fopen;

/**
 * @update 23/01/2019: inclusão da AWS cm S3 Storage
 */
class Storage {

    public $fs;
    public $error;
    public $adapterName;
    public $adapter;
    public $config;
    private $cfg;
    public $content;
    private $path;
    private static $dirTemp = 'pub';
    public $pathFileDownload;

    public function __construct($adapterName = false) {

        // carregar o arquivo de configuração
        //global $nsStorageConfig;
        $this->cfg = new Config2(Config::init());

        // validar se cron esta instalado
        if (!file_exists(__DIR__ . '/Config/cron')) {
            // Write folder content to log every five minutes.
            $job1 = new \Cron\Job\ShellJob();
            $job1->setCommand('php -f ' . __DIR__ . '/Config/cron.php >/dev/null 2>&1');
            $job1->setSchedule(new \Cron\Schedule\CrontabSchedule('*/10 * * * *'));
            file_put_contents(__DIR__ . '/Config/cron', date('c'));
        }

        /*
          if (!$_SESSION['user']['idUsuario']) { // somente com login efetuado
          throw new Exception('Acesso não permitido sem login (STG-18)');
          }
         */

        $this->adapterName = (($adapterName) ? $adapterName : $this->cfg->get('StoragePrivate'));
        ////Log::logTxt('storage-create', __LINE__ . $this->adapterName);
        try {
            $config = $this->cfg->get($this->adapterName);

            if (!is_array($config)) {
                throw new \Exception(__METHOD__ . ' Configuração não identificada: ' . $this->adapterName);
            }
            $config['pathPrefix'] = $this->cfg->get('pathPrefix');
            $config['getLinkPublic'] = 'df'; // $_SESSION['user']['idUsuario'];
            $this->config = $config;
            switch ($this->adapterName) {
                case 'GCP':
                    $storageClient = new StorageClient($config);
                    $bucket = $storageClient->bucket($config['bucket']);
                    $adapter = new GoogleStorageAdapter($storageClient, $bucket);
                    $adapter->setPathPrefix($config['pathPrefix']);
                    break;
                case 'S3':
                    $client = S3Client::factory($config);
                    $adapter = new AwsS3Adapter($client, $config['bucket'], $config['pathPrefix']);
                    $adapter->setPathPrefix($config['pathPrefix']);
                    break;
                case 'Filerun':
                    $adapter = new FilerunAdapter($config);
                    $adapter->setPathPrefix($config['pathPrefix']);
                    break;
                default: // local
                    $path = $this->cfg->get('Local')['diretorio_local'] . ((strlen($config['pathPrefix'] > 0)) ? DIRECTORY_SEPARATOR . $config['pathPrefix'] : '');
                    $adapter = new Local($path);
                    break;
            }
            ////Log::logTxt('storage-create', __LINE__ . 'Criado adapter');
            if (!$adapter) {
                throw new Exception(__METHOD__ . 'Adapter not created! ' . $this->adapterName);
            }

            $this->adapter = $adapter;

            // definição do storage de links publicos vem do config
            $this->config['StoragePublic'] = (($adapterName) ? $adapterName : $this->cfg->get('StoragePublic'));

            if (strlen($_SESSION['app']['linkPublic']) <= 5) {

                //if ($agencia instanceof Usuario) {
                $config['linkPublic'] = $this->cfg->get($this->config['StoragePublic'])['linkPublic'];

                // no filerun nao tem pq eh dinamicamente criado
                if ($this->config['StoragePublic'] === 'Filerun') {
                    // reservando dados atuais
                    $adapterOrigin = $adapter;
                    $nameOrigin = $this->adapterName;
                    $configOrigin = $this->config;
                    //Log::logTxt('storage-create', __LINE__ . 'Criar link public - Filerun');
                    // criar novo adapter
                    $this->adapterName = 'Filerun';
                    $this->config = $this->cfg->get('Filerun');

                    // weblink
                    $this->adapter = new FilerunAdapter($this->config);
                    $this->adapter->setBucket($this->config['bucket-public']);
                    $this->adapter->setPathPrefix($config['pathPrefix']);
                    $location = $this->adapter->applyPathPrefix('');
                    $opts = ['form_params' => ['path' => $this->adapter->getBucket() . $location, 'temporary' => false]];
                    $out = $this->adapter->callAPI('/files/weblink/', 'POST', $opts);
                    if ($out['data']['url']) {
                        $config['linkPublic'] = $out['data']['url'] . '&mode=default&download=1&path=';
                    } else {
                        var_export($out);
                        die('Não consegui obter o link publico no storage');
                        return false;
                    }

                    //$config['linkPublic'] = $this->getWebLink('', false) . '&mode=default&download=1&path='; //';; // obter link publico permanente
                    //restaurar após obter link
                    $this->adapter = $adapterOrigin;
                    $this->adapterName = $nameOrigin;
                    $this->adapter->setBucket($configOrigin['bucket']);
                    $this->adapter->setPathPrefix($configOrigin['pathPrefix']);
                    $this->config = $configOrigin;
                } else {
                    $config['linkPublic'] .= '/' . (($config['pathPrefix']) ? $config['pathPrefix'] . '/' : '');
                }
                $_SESSION['app']['linkPublic'] = $config['linkPublic'];
                //}
            }

            // Criação do adapter conforme definições anteriores
            $this->fs = new Filesystem($adapter);
        } catch (Exception $exc) {
            //Log::logTxt('storage-create', $exc->getMessage());
            throw new Exception('Ocorreu um erro ao criar o objeto para storage: ' . $exc->getMessage());
        }
    }

    /**
     * Ira chamar os método não atualizados dos adapters
     * @param type $name
     * @param type $arguments
     * @return type
     */
    public function __call($name, $arguments) {
        return $this->fs->$name($arguments[0], $arguments[1], $arguments[2], $arguments[3]);
    }

    /**
     * Carrega o conteudo do arquivo a ser enviado ao storage
     * @param type $pathLocal - path do arquivo a ser enviado
     * @param type $setPath - caso true: sera setado conforme nome do arquivo. Caso definido, será setado conforme definido
     * @return $this
     * @throws \Exception
     */
    public function loadFile($pathLocal, $setPath = false) {
        $path = str_replace('/', DIRECTORY_SEPARATOR, $pathLocal);
        if (!file_exists($path)) {
            throw new \Exception('Arquivo não localizado: ' . $path);
        }
        $this->path = false;
        if ($setPath) {
            if ($setPath === true) {
                $nome = str_replace($this->cfg->get('path') . DIRECTORY_SEPARATOR, '', $path);
                $setPath = str_replace(DIRECTORY_SEPARATOR, '/', $nome);
            }
            $this->path = $setPath;
        }

        $this->content = fopen($pathLocal, 'r');
        return $this;
    }

    /**
     * Define o nome do arquivo no storage
     * @param type $path
     * @return $this
     */
    public function setPath($path) {
        $this->path = $path;
        return $this;
    }

    private function validaPath($param) {
        if ($this->path === false) {
            die($param . ' - Path não definido');
        }
    }

    public function download($filenameToSave = false) {
        $this->validaPath(__METHOD__);
        //try {
        // extensao, mantem a original
        $extOriginal = mb_strtolower(array_pop(explode('.', $this->path)));

        // nome unico do arquivo
        $unique = substr(md5($this->cfg->get('pathPrefix') . $this->path), 0, 6);

        // nome do arquivo a salvar
        $t = explode('/', $this->path);
        if (!$filenameToSave) {
            $sis_name = array_pop($t);
            $filenameToSave = str_replace('/', '_', $this->path);
        }
        // aplicar unique e extensão original
        $t2 = explode('.', $filenameToSave);
        if (count($t2) > 1) {
            $name = implode('_', $t2);
        } else {
            $name = $filenameToSave;
        }
        $name = str_replace(['/', '\\'], '_', $name);


        // nome do arquivo resultante
        $toSave = \League\Flysystem\Util::normalizePath(
                        \NsStorageLibrary\Util::sanitize($name)
                        . '_'
                        . $unique
                        . '.'
                        . $extOriginal);

        // validar se existe, retornar o link, se não download antes
        if (!file_exists($this->cfg->get('path_downloads') . '/' . $toSave)) {
            $content = $this->fs->read($this->path);
            file_put_contents($this->cfg->get('path_downloads') . '/' . $toSave, $content);
        }
        $this->pathFileDownload = $this->cfg->get('path_downloads') . '/' . $toSave;
        return $this->cfg->get('url_downloads') . '/' . $toSave;
    }

    public function downloadAsString() {
        $this->validaPath(__METHOD__);
        try {
            $content = $this->fs->read($this->path);
            return $content;
        } catch (FileNotFoundException $exc) {
            return 'ST: File not found';
        } catch (Exception $exc) {
            $erro = $exc->getMessage();
            return $erro;
        }
    }

    public function setPublic() {
        try {
            $this->validaPath(__METHOD__);
            if (method_exists($this->adapter, 'setVisibility')) {
                return $this->fs->setVisibility($this->path, AdapterInterface::VISIBILITY_PUBLIC);
            }
        } catch (FileNotFoundException $exc) {
            echo $exc->getMessage();
            die();
        } catch (Exception $exc) {
            echo $exc->getMessage();
        }
    }

    public function setPrivate() {
        try {
            $this->validaPath(__METHOD__);
            if (method_exists($this->adapter, 'setVisibility')) {
                return $this->fs->setVisibility($this->path, AdapterInterface::VISIBILITY_PRIVATE);
            }
        } catch (FileNotFoundException $exc) {
            echo $exc->getMessage();
        } catch (Exception $exc) {
            echo $exc->getMessage();
        }
    }

    public function upload($public = false, $update = true) {
        $this->validaPath(__METHOD__);
        if ($public) {
            return $this->savePublic($update);
        } else {
            return $this->save($update);
        }
    }

    private function save($update = true) {
        $this->validaPath(__METHOD__);
        try {
            if (!$this->content) {
                die('Nenhum conteudo a ser enviado: ' . $this->path);
            }
            $fn = (($update) ? 'put' : 'write');
            $out = $this->fs->$fn($this->path, $this->content);
            $this->content = false;
            return $out;
        } catch (FileExistsException $exc) {
            return $this->fs->update($this->path, $this->content);
        } catch (Exception $exc) {
            $this->error = $exc->getMessage();
            return false;
        }
    }

    private function savePublic($update = true) {
        $this->validaPath(__METHOD__);
        $storage = new Storage($this->config['StoragePublic']);
        $storage->setPath($this->path);
        try {
            $storage->setBucket($storage->config['bucket-public']);
            $storage->content = $this->content;
            $out = $storage->save($update);
            if ($out) {
                $storage->setPath($this->path);
                $storage->setPublic();
            }
            unset($storage);
            return $out;
        } catch (FileNotFoundException $exc) {
            echo $exc->getMessage();
        } catch (Exception $exc) {
            $this->error = $exc;
            //file_put_contents(constant('PATH') . '/app/fr_savePublic-error.log', $exc->getMessage(), FILE_APPEND);
            ////Log::storage('SaveERROR: ' . $this->error);
            return false;
        }
    }

    /**
     * No caso do backup. precios definir obrigatoriamente o Storage de armazenamento
     * @param array $data
     * @param type $st
     * @return type
     */
    public function uploadPublicInLote(array $data, $st = false) {
        $st = (($st) ? $st : $this->config['StoragePublic']);
        $storage = new Storage($st);
        $storage->setBucket($storage->config['bucket-public']);
        foreach ($data as $item) {
            $storage->content = $item['content'];
            $out = $storage->setPath($item['path'])->save();
            if ($out) {
                $storage->setPublic();
            }
        }
        unset($storage);
        return $out;
    }

    private function delete($trash = false) {
        $this->validaPath(__METHOD__);
        try {
            if ($trash) {
                $moveTo = 'trash/' . str_replace('/', '_', $this->path);
                if ($this->adapterName === 'Filerun') {
                    $this->fs->move($this->path, $moveTo);
                } else {
                    return $this->rename($this->path, $moveTo);
                }
            } else {
                return $this->fs->delete($this->path);
            }
            return true;
        } catch (Exception $exc) {
            echo $exc->getMessage();
        }
    }

    private function deletePublic($trash = false) {
        $this->validaPath(__METHOD__);
        // selecinoar o storage public
        $storage = new Storage($this->config['StoragePublic']);
        $storage->adapter->setBucket($storage->config['bucket-public']);
        return $storage->delete($this->path, $trash);
    }

    public function cron_clearTemoraryFiles($bucket = true) {
        if ($bucket) {         // mesmo no  public
            $this->cron_clearTemoraryFiles(false); // para limpar o bucket privado
            $this->adapter->setBucket($this->config['bucket-public']);
        }
        try {
            $ret = $this->fs->listContents(self::$dirTemp);
            $date = new DateTime(date('Y-m-d H:i:s'));
            $date->setTimezone(new DateTimeZone('America/Recife'));

            foreach ($ret as $item) {
                $path = $item['path'];
                $dateFile = new DateTime(date('Y-m-d H:i:s', $item['timestamp']));
                $dateFile->setTimezone(new DateTimeZone('America/Recife'));

                if (($date->getTimestamp() - $dateFile->getTimestamp()) > (60 * 10)) {
                    $this->fs->delete($path);
                    echo "<br/>REMOVER: Date: " . $dateFile->format('Y-m-d H:i:s') . " - Path: $path";
                } else {
                    echo "<br/>NÃO REMOVER: Date: " . $dateFile->format('Y-m-d H:i:s') . " - Path: $path";
                }
            }
            return true;
        } catch (Exception $exc) {
            echo $exc->getMessage();
        }
    }

    private function setBucket($bucket) {
        if (method_exists($this->adapter, 'setBucket')) {
            $this->adapter->setBucket($bucket);
        } else {
            if ($this->adapterName !== 'GCP') {
                throw new Exception('Erro ao selecionar o Bucket (Fn Not Found)');
            }
            // por enquanto somente o GCP não tem esse método
            $storageClient = new StorageClient($this->config);
            $bucket = $storageClient->bucket($bucket);
            $adapter = new GoogleStorageAdapter($storageClient, $bucket);
            $adapter->setPathPrefix($this->config['pathPrefix']);
            $this->adapter = $adapter;
            $this->fs = new Filesystem($adapter);
        }
    }

}

// fecha classe
