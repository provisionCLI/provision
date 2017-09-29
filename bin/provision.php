<?php

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Drupal\Console\Bootstrap\Drupal;
use Drupal\Console\Core\Style\DrupalStyle;
use Drupal\Console\Core\Utils\ArgvInputReader;
use Drupal\Console\Core\Utils\ConfigurationManager;
//use Drupal\Console\Core\Utils\DrupalFinder;

use Aegir\Provision\Application;

set_time_limit(0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$autoloaders = [];

if (file_exists(__DIR__ . '/../autoload.local.php')) {
    include_once __DIR__ . '/../autoload.local.php';
} else {
    $autoloaders = [
        __DIR__ . '/../../../autoload.php',
        __DIR__ . '/../vendor/autoload.php'
    ];
}

foreach ($autoloaders as $file) {
    if (file_exists($file)) {
        $autoloader = $file;
        break;
    }
}

if (isset($autoloader)) {
    $autoload = include_once $autoloader;
} else {
    echo ' You must set up the project dependencies using `composer install`' . PHP_EOL;
    exit(1);
}

$output = new ConsoleOutput();
$input = new ArrayInput([]);
$io = new DrupalStyle($input, $output);
$argvInputReader = new ArgvInputReader();
//$root = $argvInputReader->get('root', getcwd());

//$drupalFinder = new DrupalFinder();
//if (!$drupalFinder->locateRoot($root)) {
//    $io->info('No Drupal site bootstrapped.');
//    $application = new Application();
//}
//else {
  //chdir($drupalFinder->getDrupalRoot());
//  $configurationManager = new ConfigurationManager();
//  $configuration = $configurationManager
//    ->loadConfigurationFromDirectory($drupalFinder->getComposerRoot());
//
//  if ($configuration && $options = $configuration->get('application.options') ?: []) {
//    $argvInputReader->setOptionsFromConfiguration($options);
//  }
//
//  $drupal = new Drupal($autoload, $drupalFinder);
//  $container = $drupal->boot();
//
//  if (!$container) {
//    $io->error('Something was wrong. Drupal can not be bootstrap.');
//
//    exit(1);
//  }
//
//  $application = new Application($container);
//}

//$argvInputReader->setOptionsAsArgv();
$debug = $argvInputReader->get('debug', false);
if ($debug){
    $io->writeln(
        sprintf(
            '<info>%s</info> version <comment>%s</comment>',
            Application::NAME,
            Application::VERSION
        )
    );
}

$application = new Application();
$application->run();