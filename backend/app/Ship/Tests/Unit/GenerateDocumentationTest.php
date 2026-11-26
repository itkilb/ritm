<?php

namespace App\Ship\Tests\Unit;

use App\Containers\Vendor\Documentation\Tasks\GenerateAPIDocsTask;
use App\Ship\Tests\ShipTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversNothing;
use ReflectionMethod;

#[CoversNothing]
final class GenerateDocumentationTest extends ShipTestCase
{
    public function testDocumentationCreation(): void
    {
        $sourceDocs = app_path('Containers/Vendor/Documentation');
        $sourceHeader = $sourceDocs . '/UI/WEB/Views/documentation/header.md';
        $sourceHeaderBefore = is_file($sourceHeader) ? file_get_contents($sourceHeader) : null;

        $tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ritm-docs-' . Str::random(12);
        $tempApp = $tempRoot . DIRECTORY_SEPARATOR . 'app';
        $tempDocs = $tempApp . DIRECTORY_SEPARATOR . 'Containers/Vendor/Documentation';
        $tempConfigs = $tempDocs . DIRECTORY_SEPARATOR . 'ApiDocJs/Configs';
        $tempViews = $tempDocs . DIRECTORY_SEPARATOR . 'UI/WEB/Views/';

        $originalAppPath = $this->app->path();
        $originalDocumentationConfig = config('vendor-documentation');
        $originalApiConfig = config('apiato.api');
        $originalHtmlPath = config('vendor-documentation.html_files');

        File::makeDirectory($tempDocs . '/ApiDocJs/Headers', 0755, true);
        File::makeDirectory($tempConfigs, 0755, true);
        File::makeDirectory($tempViews . 'documentation', 0755, true);
        File::copy($sourceDocs . '/ApiDocJs/apidoc.template.json', $tempDocs . '/ApiDocJs/apidoc.template.json');
        foreach (glob($sourceDocs . '/ApiDocJs/Headers/header.template.*.md') as $template) {
            File::copy($template, $tempDocs . '/ApiDocJs/Headers/' . basename($template));
        }
        File::put($tempConfigs . '/stale.json', '{}');

        $fake = new class extends GenerateAPIDocsTask {
            public array $calls = [];

            public function run($type, $console): string
            {
                $path = new ReflectionMethod(GenerateAPIDocsTask::class, 'getDocumentationPath');
                $json = new ReflectionMethod(GenerateAPIDocsTask::class, 'getJsonFilePath');
                $path->setAccessible(true);
                $json->setAccessible(true);

                $this->calls[] = [
                    'type' => $type,
                    'path' => $path->invoke($this, $type),
                    'json' => $json->invoke($this, $type),
                ];

                return '> ' . config('app.url') . '/' . config('vendor-documentation.types')[$type]['url'];
            }
        };
        $this->app->instance(GenerateAPIDocsTask::class, $fake);

        try {
            $this->app->useAppPath($tempApp);
            config([
                'vendor-documentation.html_files' => $tempViews,
                'apiato.api.throttle.expires' => 1,
                'apiato.api.throttle.attempts' => 30,
                'apiato.api.expires-in' => 60,
                'apiato.api.refresh-expires-in' => 20160,
            ]);

            $this->artisan('apiato:apidoc')
                ->expectsOutputToContain('Generating API Documentations for (public & private)')
                ->assertSuccessful();

            $this->assertCount(2, $fake->calls);
            $this->assertSame(['public', 'private'], array_column($fake->calls, 'type'));

            foreach ($fake->calls as $call) {
                $this->assertSame($tempViews . 'documentation/' . $call['type'], $call['path']);
                $this->assertSame($tempConfigs . '/apidoc.' . $call['type'] . '.json', $call['json']);
            }

            $appName = config('app.name');
            $expectedUrl = config('apiato.api.url') . rtrim((string) config('apiato.api.prefix'), '/');
            $this->assertFileDoesNotExist($tempConfigs . '/stale.json');

            $public = json_decode(file_get_contents($tempConfigs . '/apidoc.public.json'), true, 512, JSON_THROW_ON_ERROR);
            $private = json_decode(file_get_contents($tempConfigs . '/apidoc.private.json'), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame($appName, $public['name']);
            $this->assertSame($appName . ' (Public API) Documentation', $public['description']);
            $this->assertSame('Welcome to ' . $appName, $public['title']);
            $this->assertSame($expectedUrl, $public['url']);
            $this->assertSame($expectedUrl, $public['sampleUrl']);
            $this->assertSame($appName . ' (Private API) Documentation', $private['description']);
            $this->assertSame($expectedUrl, $private['url']);

            $header = file_get_contents($tempViews . 'documentation/header.md');
            $this->assertStringContainsString((string) config('apiato.api.url'), $header);
            $this->assertStringContainsString('The rate limit window is `1` minutes per endpoint, with most individual calls allowing for `30` requests', $header);
            $this->assertStringNotContainsString('api.domain.test', $header);
            $this->assertStringNotContainsString('{{rate-limit-expires}}', $header);
            $this->assertStringNotContainsString('{{pagination-limit}}', $header);
        } finally {
            $this->app->useAppPath($originalAppPath);
            config([
                'vendor-documentation' => $originalDocumentationConfig,
                'apiato.api' => $originalApiConfig,
                'vendor-documentation.html_files' => $originalHtmlPath,
            ]);
            $this->app->forgetInstance(GenerateAPIDocsTask::class);
            File::deleteDirectory($tempRoot);
        }

        if ($sourceHeaderBefore === null) {
            $this->assertFileDoesNotExist($sourceHeader);
        } else {
            $this->assertSame($sourceHeaderBefore, file_get_contents($sourceHeader));
        }
    }
}
