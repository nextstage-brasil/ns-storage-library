<?php

namespace NsStorageLibrary\Storage\Adapter;

use League\Flysystem\Adapter\Local;

// extensao de adapter local para criar a funcao setbucket

class MyLocalAdapter extends Local {

    private $path = '/ROOT/HOME';
    private $pathInit;

    public function __construct($path) {
        $this->path = $path;
        parent::__construct($path);
    }

    // Definição da pasta inicial
    public function setBucket($bucket) {
        $this->pathInit = $this->path . (($bucket) ? '/' . $bucket . '/' : '/');
        $this->setPathPrefix($bucket);
    }

    public function getBucket() {
        return $this->pathInit;
    }

}
