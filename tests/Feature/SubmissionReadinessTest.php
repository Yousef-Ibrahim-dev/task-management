<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Guards the deliverables a reviewer receives: the documented route names, and
 * a Postman collection whose requests still resolve against the real router.
 */
class SubmissionReadinessTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function routeNames(): array
    {
        $names = [
            'api.v1.auth.register',
            'api.v1.auth.login',
            'api.v1.auth.logout',
            'api.v1.auth.me',
            'api.v1.projects.index',
            'api.v1.projects.store',
            'api.v1.projects.show',
            'api.v1.projects.update',
            'api.v1.projects.destroy',
            'api.v1.projects.archive',
            'api.v1.projects.restore-status',
            'api.v1.projects.tasks.index',
            'api.v1.projects.tasks.store',
            'api.v1.projects.tasks.show',
            'api.v1.projects.tasks.update',
            'api.v1.projects.tasks.destroy',
            'api.v1.dashboard',
        ];

        return array_combine($names, array_map(fn (string $name): array => [$name], $names));
    }

    #[DataProvider('routeNames')]
    public function test_the_documented_route_is_registered(string $name): void
    {
        $this->assertTrue(Route::has($name), "route [{$name}] is missing");
    }

    public function test_no_web_application_route_exists(): void
    {
        $webUris = collect(Route::getRoutes()->getRoutesByMethod()['GET'] ?? [])
            ->keys()
            ->reject(fn (string $uri): bool => str_starts_with($uri, 'api/')
                || in_array($uri, ['up', 'sanctum/csrf-cookie'], true)
                || str_starts_with($uri, 'storage/'));

        $this->assertEmpty($webUris->all(), 'unexpected web routes: '.$webUris->implode(', '));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function postmanFiles(): array
    {
        return [
            'collection' => ['postman/Task-Management-API.postman_collection.json'],
            'environment' => ['postman/Task-Management-Local.postman_environment.json'],
        ];
    }

    #[DataProvider('postmanFiles')]
    public function test_the_postman_file_is_valid_json(string $path): void
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents, "{$path} is missing");
        $this->assertIsArray(json_decode($contents, true), "{$path} is not valid JSON: ".json_last_error_msg());
    }

    public function test_the_postman_environment_declares_the_variables_the_collection_uses(): void
    {
        $environment = $this->decodeJsonFile('postman/Task-Management-Local.postman_environment.json');
        $declared = array_column($environment['values'], 'key');

        foreach (['base_url', 'token', 'second_token', 'project_id', 'task_id', 'archived_project_id', 'completed_project_id'] as $key) {
            $this->assertContains($key, $declared, "environment is missing {{{$key}}}");
        }
    }

    public function test_every_postman_request_resolves_against_the_real_router(): void
    {
        $collection = $this->decodeJsonFile('postman/Task-Management-API.postman_collection.json');
        $checked = 0;

        foreach ($collection['item'] as $folder) {
            foreach ($folder['item'] as $item) {
                $method = $item['request']['method'];
                // Postman variables stand in for ids; any numeric value resolves
                // the same route because the parameters are constrained to digits.
                $uri = '/'.implode('/', array_map(
                    fn (string $segment): string => str_starts_with($segment, '{{') ? '1' : $segment,
                    $item['request']['url']['path'],
                ));

                try {
                    Route::getRoutes()->match(Request::create($uri, $method));
                } catch (HttpException) {
                    $this->fail("Postman request [{$item['name']}] points at {$method} {$uri}, which no route matches");
                }

                $checked++;
            }
        }

        $this->assertGreaterThan(20, $checked);
    }

    public function test_composer_defines_the_scripts_the_readme_documents(): void
    {
        $composer = $this->decodeJsonFile('composer.json');

        foreach (['test', 'lint', 'analyse', 'check'] as $script) {
            $this->assertArrayHasKey($script, $composer['scripts'], "composer script [{$script}] is documented but missing");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonFile(string $path): array
    {
        $decoded = json_decode((string) file_get_contents(base_path($path)), true);

        $this->assertIsArray($decoded, "{$path} is not valid JSON");

        return $decoded;
    }
}
