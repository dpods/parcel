<?php

namespace Dpods\Parcel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class MakePackage extends Command
{
    public $signature = 'make:package';

    public $description = 'Create a new package';

    public function handle()
    {
        $this->info('Generating new package with namespace Vendor\PackageName');
        $vendor            = $this->ask('Vendor?');
        $packageName       = $this->ask('PackageName?');
        $authorName        = $this->ask('Author Name?', '');
        $authorEmail       = $this->ask('Author Email?', '');
        $packagesDirectory = base_path(config('parcel.directory'));
        $parcelDirectory   = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..']);
        $stubsDirectory    = implode(DIRECTORY_SEPARATOR, [$parcelDirectory, 'stubs']);

        if (!is_dir($packagesDirectory)) {
            $this->info('Creating package directory:');
            $this->line($packagesDirectory);
            mkdir($packagesDirectory, 0775, true);
        }

        $newPackageDirectory = implode(DIRECTORY_SEPARATOR, [$packagesDirectory, strtolower("{$vendor}-{$packageName}")]);
        if (is_dir($newPackageDirectory)) {
            $this->error('Package already exists at ' . $newPackageDirectory);
        }

        $this->info('Creating new package:');
        $this->line($newPackageDirectory);
        mkdir($newPackageDirectory, 0775, true);

        $this->createDirectories(
            $newPackageDirectory,
            [
                ['config'],
                ['database', 'factories'],
                ['database', 'migrations'],
                ['resources', 'views'],
                ['app', 'Http', 'Controllers'],
                ['routes'],
                ['tests'],
            ]
        );

        $this->copyAndReplaceValues(
            [$stubsDirectory, 'composer.json.stub'],
            [$newPackageDirectory, 'composer.json'],
            [
                '<VENDOR>'             => $vendor,
                '<VENDOR_LOWER>'       => strtolower($vendor),
                '<PACKAGE_NAME>'       => $packageName,
                '<PACKAGE_NAME_LOWER>' => strtolower($packageName),
                '<AUTHOR_NAME>'        => $authorName,
                '<AUTHOR_EMAIL>'       => $authorEmail
            ]
        );

        $this->copyAndReplaceValues(
            [$stubsDirectory, 'app', 'PACKAGE_NAMEServiceProvider.php.stub'],
            [$newPackageDirectory, 'app', "{$packageName}ServiceProvider.php"],
            [
                '<VENDOR>'             => $vendor,
                '<PACKAGE_NAME>'       => $packageName,
                '<PACKAGE_NAME_LOWER>' => strtolower($packageName),
            ]
        );

        $this->copyAndReplaceValues(
            [$stubsDirectory, 'config', 'PACKAGE_NAME_LOWER.php.stub'],
            [$newPackageDirectory, 'config', strtolower($packageName) . ".php"],
        );

        $this->copyAndReplaceValues(
            [$stubsDirectory, 'routes', 'web.php.stub'],
            [$newPackageDirectory, 'routes', "web.php"],
            [
                '<VENDOR>'             => $vendor,
                '<PACKAGE_NAME>'       => $packageName,
            ]
        );

        $this->copyAndReplaceValues(
            [$stubsDirectory, 'app', 'Http', 'Controllers', 'Controller.php.stub'],
            [$newPackageDirectory, 'app', 'Http', 'Controllers', 'Controller.php'],
            [
                '<VENDOR>'             => $vendor,
                '<PACKAGE_NAME>'       => $packageName,
            ]
        );

        $this->copyAndReplaceValues(
            [$stubsDirectory, 'app', 'Http', 'Controllers', 'WelcomeController.php.stub'],
            [$newPackageDirectory, 'app', 'Http', 'Controllers', 'WelcomeController.php'],
            [
                '<VENDOR>'             => $vendor,
                '<PACKAGE_NAME>'       => $packageName,
                '<PACKAGE_NAME_LOWER>' => strtolower($packageName),
            ]
        );

        $this->copyAndReplaceValues(
            [$stubsDirectory, 'resources', 'views', 'welcome.blade.php.stub'],
            [$newPackageDirectory, 'resources', 'views', "welcome.blade.php"],
            [
                '<PACKAGE_NAME>'       => $packageName,
            ]
        );

        $this->copyAndReplaceValues(
            [$stubsDirectory, 'tests', 'TestCase.php.stub'],
            [$newPackageDirectory, 'tests', 'TestCase.php'],
            [
                '<PACKAGE_NAME>'       => $packageName,
            ]
        );

        $this->updateComposerJsonFile(config('parcel.directory'), $vendor, $packageName);

        $this->info('Success');
    }

    /**
     * @param string $packageFilePath
     * @param array $directories
     */
    protected function createDirectories(string $packageFilePath, array $directories)
    {
        $this->info("Creating directories:");

        foreach ($directories as $pathParts) {
            mkdir(
                implode(DIRECTORY_SEPARATOR, [
                    $packageFilePath,
                    ...$pathParts
                ]),
                0775,
                true
            );

            $this->line(implode(DIRECTORY_SEPARATOR, [
                $packageFilePath,
                ...$pathParts
            ]));
        }
    }

    /**
     * @param array $stubFilepath
     * @param array $packageFilePath
     * @param array $variables
     */
    protected function copyAndReplaceValues(array $stubFilepath, array $packageFilePath, array $variables = [])
    {
        $stubFilepath = implode(DIRECTORY_SEPARATOR, $stubFilepath);
        $packageFilePath = implode(DIRECTORY_SEPARATOR, $packageFilePath);

        copy($stubFilepath, $packageFilePath);

        $contents = file_get_contents($packageFilePath);
        foreach ($variables as $placeholder => $value) {
            $contents = str_replace($placeholder, $value, $contents);
        }
        file_put_contents($packageFilePath, $contents);
    }

    /**
     * @param string $packagesDirectory
     * @param string $vendor
     * @param string $packageName
     */
    protected function updateComposerJsonFile(string $packagesDirectory, string $vendor, string $packageName)
    {
        $this->line("Updating composer.json");

        $contents = json_decode(file_get_contents(base_path('composer.json')), true);

        if (!Arr::has($contents, 'repositories')) {
            $contents['repositories'] = [];
        }

        $addRepository = true;
        foreach ($contents['repositories'] as $repository) {
            if (Arr::has($repository, 'type') && $repository['type'] == 'path'
                && Arr::has($repository, 'url') && $repository['url'] == "./{$packagesDirectory}/*") {
                $addRepository = false;
            }
        }

        if ($addRepository) {
            $contents['repositories'][] = [
                'type' => 'path',
                'url'  => "./{$packagesDirectory}/*"
            ];
        }

        if (!Arr::has($contents, 'require')) {
            $contents['require'] = [];
        }

        $vendorLower      = strtolower($vendor);
        $packageNameLower = strtolower($packageName);
        $contents['require']["{$vendorLower}/{$packageNameLower}"] = 'dev-master';

        file_put_contents(base_path('composer.json'), json_encode($contents, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
