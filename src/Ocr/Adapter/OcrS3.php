<?php

namespace NsStorageLibrary\Ocr\Adapter;

use Aws\Textract\TextractClient;
use Exception;
use League\Flysystem\Config as Config2;
use NsStorageLibrary\Config;
use NsStorageLibrary\Ocr\Exception\RequestCreatedException;
use function GuzzleHttp\json_encode;

class OcrS3 implements OcrInterface {

    //https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-textract-2018-06-27.html#detectdocumenttext
    //https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-textract-2018-06-27.html#startdocumenttextdetection

    private $client, $cfg, $queueFile;

    public function __construct() {
        $init = Config::init();
        $init['S3']['version'] = '2018-06-27';
        $this->cfg = new Config2($init);
        $this->client = new TextractClient($this->cfg->get('S3'));

        // fila de idjob para leitura de OCR assincrono
        $dir = $this->cfg->get('path_tmp') . '/OcrS3';
        $this->queueFile = $dir.'/aws_queue.ns';
        if (!file_exists($this->queueFile)) {
            mkdir($dir, 0777, true);
            file_put_contents($this->queueFile, json_encode([]));
        }
    }

    public function create_request($path, $wait = false) {
        // validar se existe request anterior
        $job = $this->queue($path, 'read');
        if ($job) {
            return $job;
        }
        // se não, criar
        $data = [
            //'ClientRequestToken' => '<string>',
            'DocumentLocation' => [// REQUIRED
                'S3Object' => [
                    'Bucket' => $this->cfg->get('S3')['bucket'],
                    'Name' => $this->cfg->get('pathPrefix') . '/' . $path
                ],
            ],
            'JobTag' => 'JOBTAG',
            'NotificationChannel' => $this->cfg->get('S3')['SNS'],
        ];
        $ret = $this->client->startDocumentTextDetection($data);
        $this->queue($path, 'add', $ret->get('JobId'));
        return $ret->get('JobId');
    }

    public function detect_document_text_gcs($path) {
        $out = $this->readFromRequest($path); // primeiro vamos ver se existe uma fila já enviada
        if (!$out) { // se nao, criar a requisição
            $data = ['Document' => [// REQUIRED
                    'S3Object' => [
                        'Bucket' => $this->cfg->get('S3')['bucket'],
                        'Name' => $this->cfg->get('pathPrefix') . '/' . $path
                    ],
                ],
            ];
            $result = $this->client->detectDocumentText($data);
            $out = $this->block2Txt($result);
        }
        return $out;
    }

    public function detect_pdf_gcs($path) {
        $out = $this->readFromRequest($path); // primeiro vamos ver se existe uma fila já enviada
        if (!$out) { // se nao, criar a requisição
            $this->create_request($path);
            throw new RequestCreatedException($path);
        }
        return $out;
    }

    public function has_json($path) {
        try {
            return (boolean) $this->readFromRequest($path);
        } catch (Exception $exc) {
            return false;
        }
    }

    /**
     * Le uma requisição já feita, e avalia o estado. Se SUCCESS, libera o OCR identificado, se não retorna excpetion
     * @param type $path
     */
    private function readFromRequest($path) {
        $job = $this->queue($path, 'read');
        if ($job) {
            $data = [
                'JobId' => $job, // NECESSÁRIO
                'MaxResults' => 1000,
            ];
            $result = $this->client->getDocumentTextDetection($data);
            if ($this->isComplete($result)) {
                $ocr = $this->block2Txt($result);
            }
            if ($this->isFailed($result)) {
                $ocr = $result['StatusMessage'];
            }
        }
        return $ocr;
    }

    /**
     * Controle da fila enviada ao assincrono ocr
     * @param type $path
     * @param type $action
     * @param type $JobId
     * @return boolean
     */
    private function queue($path, $action, $JobId = '') {
        $chave = md5($path);
        $json = json_decode(file_get_contents($this->queueFile), true);
        switch ($action) {
            case 'add':
                $json[$chave] = [
                    'created' => time(),
                    'id' => $JobId
                ];
                file_put_contents($this->queueFile, json_encode($json));
                $out = true;
                break;
            case 'read':
                $out = $json[$chave]['id'];
                break;
            case 'remove':
                unset($json[$chave]);
                file_put_contents($this->queueFile, json_encode($json));
                $out = true;
                break;
        }
        return $out;
    }

    private function block2Txt($result) {
        $out = ['[NsStorageLibraryOCR-S3]'];
        foreach ($result['Blocks'] as $block) {
            $out[] = $block['Text'];
        }
        return implode(' ', $out);
    }

    private function isComplete($result) {
        return $result['JobStatus'] === 'SUCCEEDED';
    }

    private function isFailed($result) {
        return $result['JobStatus'] === 'FAILED';
    }

}
