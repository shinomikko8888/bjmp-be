<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit2da2987934ba313d598520404e209a69
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Picqer\\Barcode\\' => 15,
            'PhpOffice\\PhpWord\\' => 18,
            'PHPMailer\\PHPMailer\\' => 20,
        ),
        'N' => 
        array (
            'NcJoes\\OfficeConverter\\' => 23,
        ),
        'L' => 
        array (
            'Laminas\\Escaper\\' => 16,
        ),
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Picqer\\Barcode\\' => 
        array (
            0 => __DIR__ . '/..' . '/picqer/php-barcode-generator/src',
        ),
        'PhpOffice\\PhpWord\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpoffice/phpword/src/PhpWord',
        ),
        'PHPMailer\\PHPMailer\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpmailer/phpmailer/src',
        ),
        'NcJoes\\OfficeConverter\\' => 
        array (
            0 => __DIR__ . '/..' . '/ncjoes/office-converter/src/OfficeConverter',
        ),
        'Laminas\\Escaper\\' => 
        array (
            0 => __DIR__ . '/..' . '/laminas/laminas-escaper/src',
        ),
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit2da2987934ba313d598520404e209a69::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit2da2987934ba313d598520404e209a69::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit2da2987934ba313d598520404e209a69::$classMap;

        }, null, ClassLoader::class);
    }
}
