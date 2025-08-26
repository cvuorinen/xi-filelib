<?php

require __DIR__ . '/../tests/bootstrap.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * @author Mikko Hirvonen <mikko.petteri.hirvonen@gmail.com>
 */
class SchemaGenerator
{
    /**
     * @var array
     */
    private $connectionOptions;

    /**
     * @param  array           $connectionOptions
     * @return SchemaGenerator
     */
    public function __construct(array $connectionOptions)
    {
        $this->connectionOptions = $connectionOptions;
    }

    /**
     * Generate SQL for creating schema
     *
     * @return string
     */
    public function generate()
    {
        $config = ORMSetup::createAnnotationMetadataConfiguration(
            [__DIR__ . '/../library/Xi/Filelib/Backend/DoctrineOrm/Entity'],
            true, // isDevMode
            false // useSimpleAnnotationReader
        );

        $config->setProxyDir(ROOT_TESTS . '/data/temp');
        $config->setProxyNamespace('Proxies');

        $connection = DriverManager::getConnection($this->connectionOptions, $config);
        $em = new EntityManager($connection, $config);

        $st = new SchemaTool($em);
        $metadata = $st->getCreateSchemaSql($em->getMetadataFactory()->getAllMetadata());

        return join(";\n", $metadata) . ";\n";
    }
}

if ($argc < 2) {
    echo <<<EOT
usage: php $argv[0] <driver>

example: php $argv[0] sqlite
example: php $argv[0] mysql
example: php $argv[0] pgsql

EOT;

    die;
}

$options['driver'] = 'pdo_' . $argv[1];
$options['host'] = '127.0.0.1';
// $options['username'] = 'michalis-rakintzis';
// $options['password'] = 'sagapo';
// $options['dbname'] = 'dbname';

$generator = new SchemaGenerator($options);

echo $generator->generate();
