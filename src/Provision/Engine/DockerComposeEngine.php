<?php

namespace Aegir\Provision\Engine;

use Aegir\Provision\Context\ServerContext;
use Aegir\Provision\Engine\EngineInterface;
use Aegir\Provision\Provision;
use Aegir\Provision\Service\DockerServiceInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ServerContextDockerCompose
 *
 * @package Aegir\Provision\Context
 */
class DockerComposeEngine implements EngineInterface {

    /**
     * @var \Aegir\Provision\Context\ServerContext
     */
    public $server;

    const DOCKER_COMPOSE_COMMAND = 'docker-compose';
    const DOCKER_COMPOSE_UP_OPTIONS = ' -d --build --force-recreate ';

    /**
     * ServerContextDockerCompose constructor.
     *
     * @param \Aegir\Provision\Context\ServerContext $server
     */
    function __construct(ServerContext $server) {
        $this->server = $server;
    }

    public function preVerify() {
        $steps = [];
        $filename = $this->server->getProperty('server_config_path') . DIRECTORY_SEPARATOR . 'docker-compose.yml';

      // Write docker-compose.yml file.
      $steps['docker.compose.write'] = Provision::newStep()
        ->start('Generating docker-compose.yml file...')
        ->success('Generating docker-compose.yml file... Saved to ' . $filename)
        ->failure('Generating docker-compose.yml file... Saved to ' . $filename)
        ->execute(function () use ($filename) {

          // Load docker compose data from each docker service.
          foreach ($this->server->getServices() as $type => $service) {
            if ($service instanceof DockerServiceInterface) {
              $compose_services[$type] = $service->dockerComposeService();
              $compose_services[$type]['hostname'] = $this->server->name . '.' . $type;

              // Look for Dockerfile overrides for this service.
              $dockerfile_override_path = $this->server->server_config_path . DIRECTORY_SEPARATOR . 'Dockerfile.' . $type;
              if (file_exists($dockerfile_override_path)) {
                $this->server->getProvision()->getTasks()->taskLog("Found custom Dockerfile for service {$type}: {$dockerfile_override_path}", LogLevel::INFO)->run()->getExitCode();

                $compose_services[$type]['image'].= '-custom';
                $compose_services[$type]['build']['context'] = '.';
                $compose_services[$type]['build']['dockerfile'] = 'Dockerfile.' . $type;
                $compose_services[$type]['environment']['PROVISION_CUSTOM_DOCKERFILE'] = $dockerfile_override_path;
              }

            }
          }

          // If there are any docker services in this server create a
          // docker-compose file.
          $compose = array(
            'version' => '2',
            'services' => $compose_services,
          );

          $server_name = $this->server->name;
          $yml_prefix = <<<YML
# Provision Docker Compose File
# =============================
# Server: $server_name
#
# $filename
# 
# DO NOT EDIT THIS FILE.
# This file was automatically generated by Provision CLI.
#
# To re-generate this file, run the command:
#
#    provision verify $server_name
#
#
# Overrides
# =========
#
# To customize this Docker cluster, create a docker-compose-overrides.yml file 
# in the same folder as this file. 
#
# If this file exists, it will be included in the `docker-compose` command when
# the `provision verify` command is run.
#

YML;
          $yml_dump = $yml_prefix . Yaml::dump($compose, 5, 2);
          $debug_message = 'Generated Docker Compose file: ' . PHP_EOL . $yml_dump;
          $this->server->getProvision()->getTasks()->taskLog($debug_message, LogLevel::INFO)->run()->getExitCode();

          $this->server->fs->dumpFile($filename, $yml_dump);

          // Write .env file to tell docker-compose to use all of the docker-compose-*.yml files.
          $dc_files = $this->findDockerComposeFiles();
          $files = [];
          foreach ($dc_files as $file) {
            $files[] = $file->getFilename();
          }
          $dc_files_path = implode(':', $files);

          // Allow users to set a .env-custom file to allow additional ENV for the folder to be preserved.
          $env_file_path = $this->server->getProperty('server_config_path') . '/.env';
          $env_custom_path = $this->server->getProperty('server_config_path') . '/.env-custom';
          $env_custom = file_exists($env_custom_path)? '# LOADED FROM .env-custom: ' . PHP_EOL . file_get_contents($env_custom_path): '';
          $env_file_contents = <<<ENV
# Provision-generated file. Do not edit.
# Add a file .env-custom and it will be included here on `provision-verify`
# For available docker-compose env vars, see https://docs.docker.com/compose/reference/envvars/
COMPOSE_PATH_SEPARATOR=:
COMPOSE_FILE=$dc_files_path
$env_custom
ENV;
          $this->server->fs->dumpFile($env_file_path, $env_file_contents);
          $debug_message = 'Generated .env file for docker-compose: ' . PHP_EOL . $yml_dump;
          $this->server->getProvision()->getTasks()->taskLog($debug_message, LogLevel::INFO)->run()->getExitCode();
        });

        return $steps;
    }

    /**
     * @return array
     */
    public function postVerify() {

        // Run docker-compose up with options
        $command = $this->dockerComposeCommand('up', $this::DOCKER_COMPOSE_UP_OPTIONS);
        $steps['docker.compose.up'] = Provision::newStep()
            ->start("Running <info>{$command}</info> in <info>{$this->server->server_config_path}</info> ...")
            ->execute(function() use ($command) {
                return $this->server->shell_exec($command, NULL, 'exit');
            })
        ;

        return $steps;
    }

    /**
     * @return \Symfony\Component\Finder\SplFileInfo[]
     */
    function findDockerComposeFiles() {
        $finder = new Finder();
        $finder->in($this->server->getProperty('server_config_path'));
        $finder->files()->name('docker-compose*.yml');
        foreach ($finder as $file) {
            $dc_files[] = $file;
        }
        return $dc_files;
    }


    /**
     * Return the base docker-compose command with options automatically populated.
     *
     * @param string $command
     * @param string $options
     * @param bool $load_files If TRUE, all docker-compose*.tml files
     *   will be found and added using the `-f` option.
     *
     *   This defaults to false because Provision writes the .env file to the
     *   server_config_path, which includes all the files. As long as the command
     *   is run in that folder, you don't need to set $load_files.
     *
     * @return string
     */
    function dockerComposeCommand($command = '', $options = '', $load_files = FALSE) {

        // Generate the docker-compose command.
        $docker_compose = self::DOCKER_COMPOSE_COMMAND;

        // If told to load files, do it.
        if ($load_files) {
            foreach ($this->findDockerComposeFiles() as $file) {
                $docker_compose .= ' -f ' . $file->getPathname();
            }
        }

        $command = "{$docker_compose} {$command} {$options}";
        return $command;
    }
}