<?php

/**
 * Arquivo de configuração do NsStorageLibrary
 * Importante: não altere o nome das variaveis
 */
$nsBucketName = 'my_application'; // nome da pasta ou diretorio principal que ira comportar os arquivos
$nsBucketNamePublic = $nsBucketName; // Use o mesmo nome de bucketname para controlar somente por visibilidade, ou pasta separada caso queira
$nsStorageConfig = [
    'url' => '', // url da aplicação, ex: https://www.meusite.com
    'path' => '', // path absoluto da aplicação, ex.: /home/my_application/www
    'dir_to_write' => 'app', // diretorio em relação ao path com permissão de escrita
    'pathPrefix' => '', // pasta prefixo a ser aplicada em todos arquivos. Util para criação dinamica conforme cliente logado
    'StoragePrivate' => 'S3', // define em qua storage deve ser armazenado os arquivos privados. Opções: Local | Filerun | S3 | GCP
    'StoragePublic' => 'S3', // define em qua storage deve ser armazenado os arquivos publicos (thumbs)
    'Local' => [
        'dir' => 'st' // em relação a path, com permissão de escrita
    ],
    'S3' => [// Storage da AWS
        'credentials' => [// obtido direto no console do S3 contratado
            'key' => '',
            'secret' => '',
        ],
        'region' => 'us-east-2', // região do S3
        'SNS' => [// required to OCR 
            'RoleArn' => '', // O Amazon Resource Name (ARN) de uma função do IAM que concede permissões de publicação do Amazon Textract ao tópico Amazon SNS.
            'SNSTopicArn' => '', // O tópico Amazon SNS no qual o Amazon Textract publica o status de conclusão.
        ]
    ],
    'GCP' => [// Storage do Google Cloud Platform
        'projectId' => '',
        'keyFilePath' => '' // path absoluto onde esta o arquivo de acesso ao Google Cloud Plataform
    ],
    'Filerun' => [// dados do servidor de armazenamento de arquivos, para uso na API
        'url' => '', // url do servidor filerun
        'client_id' => '',
        'client_secret' => '',
        'username' => '',
        'password' => '',
    ],
];
