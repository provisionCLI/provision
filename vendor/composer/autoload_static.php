<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit93ce438f8ce8708ef24439d2070dee06
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Symfony\\Component\\Process\\' => 26,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Component\\Process\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/process',
        ),
    );

    public static $prefixesPsr0 = array (
        'P' => 
        array (
            'Provision_' => 
            array (
                0 => __DIR__ . '/../..' . '/Provision',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit93ce438f8ce8708ef24439d2070dee06::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit93ce438f8ce8708ef24439d2070dee06::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit93ce438f8ce8708ef24439d2070dee06::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}