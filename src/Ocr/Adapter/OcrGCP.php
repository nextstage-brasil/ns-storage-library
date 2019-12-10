<?php

namespace NsStorageLibrary\Ocr\Adapter;

//namespace Google\Cloud\Samples\Vision;


use Exception;
use Google\Cloud\Vision\V1\AsyncAnnotateFileRequest;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\GcsDestination;
use Google\Cloud\Vision\V1\GcsSource;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\InputConfig;
use Google\Cloud\Vision\V1\OutputConfig;
use League\Flysystem\Config as Config2;
use NsStorageLibrary\Config;
use NsStorageLibrary\Storage\Storage;

class OcrGCP implements OcrInterface {

    private $path, $storage, $cfg;

    public function __construct() {
        $this->cfg = new Config2(Config::init());
        // variavel de ambiente para conexão com google cloud
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->cfg->get('GCP')['keyFilePath']);
        $this->path = 'gs://' . $this->cfg->get('GCP')['bucket'] . '/' . $this->cfg->get('pathPrefix'); //Config::getData('storage', 'prefix');
        $this->storage = new \Google\Cloud\Storage\StorageClient();
        $this->bucket = $this->storage->bucket($this->cfg->get('GCP')['bucket']);
    }

    public function has_json($path) {
        $output = md5($path);
        try {
            $options = ['prefix' => 'ocr/' . $output];
            foreach ($this->bucket->objects($options) as $object) {
                //printf('Object: %s' . PHP_EOL, $object->name());
            }
            if (!$object) {
                return false;
            }
            return true;
        } catch (Exception $exc) { // não existe ainda..
            //echo 'Não existe, criar request' . PHP_EOL;
            return false;
        }
    }

    public function create_request($path, $wait = false) {

        $output = md5($path);

        $path = $this->path . '/' . $path;
        $output = 'ocr/' . $output;


        # select ocr feature
        $feature = (new Feature())
                ->setType(Type::DOCUMENT_TEXT_DETECTION);

        # set $path (file to OCR) as source
        $gcsSource = (new GcsSource())
                ->setUri($path);
        # supported mime_types are: 'application/pdf' and 'image/tiff'
        $mimeType = 'application/pdf';
        $inputConfig = (new InputConfig())
                ->setGcsSource($gcsSource)
                ->setMimeType($mimeType);

        # set $output as destination
        $gcsDestination = (new GcsDestination())
                ->setUri($output);
        # how many pages should be grouped into each json output file.
        $batchSize = 2;
        $outputConfig = (new OutputConfig())
                ->setGcsDestination($gcsDestination)
                ->setBatchSize($batchSize);

        # prepare request using configs set above
        $request = (new AsyncAnnotateFileRequest())
                ->setFeatures([$feature])
                ->setInputConfig($inputConfig)
                ->setOutputConfig($outputConfig);
        $requests = [$request];
        # make request
        $imageAnnotator = new ImageAnnotatorClient();
        $operation = $imageAnnotator->asyncBatchAnnotateFiles($requests);
        //print('Waiting for operation to finish: ' . $path . PHP_EOL);
        if ($wait) {
            $operation->pollUntilComplete();
        }
        return true;
    }

    /**
     * 
     * @param type $path ARRAY or STRING
     * @return type
     */
    public function detect_pdf_gcs($path) {
        if (!$this->has_json($path)) {
            $this->create_request($path);
            throw new \NsStorageLibrary\Ocr\Exception\RequestCreatedException('Criado requisição no GCP: ' . $path);
        }

        if (!$this->has_json($path)) {
            return 'NS';
        }
        $prefix = md5($path);
        $options = ['prefix' => 'ocr/' . $prefix];
        $out = ['[NsStorageLibraryOCR-GCP]'];
        foreach ($this->bucket->objects($options) as $object) {
            $jsonString = json_decode($object->downloadAsString(), true);
            foreach ($jsonString['responses'] as $val) {
                $out[] = $val['fullTextAnnotation']['text'];
            }
        }

        //$object->delete();  

        return implode(' ', $out);


    }

    function detect_document_text_gcs($path) {
        $imageAnnotator = new ImageAnnotatorClient();
        $response = $imageAnnotator->documentTextDetection($this->path . '/' . $path);
        $imageAnnotator->close();
        if ($response->getFullTextAnnotation()) {
            return '[NsStorageLibraryOCR-GCP]' . $response->getFullTextAnnotation()->getText();
        } else {
            return '[NsStorageLibraryOCR-GCP] Erro ao identificar ' . $this->path . '/' . $path;
        }
    }

    function getPath() {
        return $this->path;
    }

    function getStorage() {
        return $this->storage;
    }

}
