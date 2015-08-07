<?php

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;

// $configurator->setDebugMode(FALSE); // enable for your remote IP
$configurator->enableDebugger(__DIR__ . '/../log');

$configurator->setTempDirectory(__DIR__ . '/../temp');

$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->addDirectory(__DIR__ . '/../libs/')
	->setCacheStorage(new Nette\Caching\Storages\FileStorage(__DIR__ . '/../temp'))
	->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');
$configurator->addConfig(__DIR__ . '/config/config.local.neon'); // disable for your remote IP

$container = $configurator->createContainer();

return $container;
