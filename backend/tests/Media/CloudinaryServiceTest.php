<?php

declare(strict_types=1);

namespace App\Tests\Media;

use App\Media\CloudinaryService;
use App\Shared\Clock\FrozenClock;
use App\Tests\Support\FakePsr18Client;
use Cloudinary\Api\ApiUtils;
use Cloudinary\Cloudinary;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CloudinaryServiceTest extends TestCase
{
    private const CLOUDINARY_URL = 'cloudinary://test_key:test_secret@test_cloud';
    private const ROOT = 'skimr-dev';
    private const NOW = '2026-11-26 20:00:00';

    private FakePsr18Client $http;

    private ?string $temporaryFile = null;

    protected function tearDown(): void
    {
        if ($this->temporaryFile !== null && is_file($this->temporaryFile)) {
            unlink($this->temporaryFile);
        }
    }

    public function testSignsPythonUploadParametersWithTheExpectedResourceTypeAndFolder(): void
    {
        $service = $this->service();
        $params = $service->signedUploadParams(
            'sources/4afc2d11-1111-4111-8111-111111111111/57d3ef22-2222-4222-8222-222222222222/file',
            'raw',
        );
        $expectedParameters = [
            'public_id' => self::ROOT . '/sources/4afc2d11-1111-4111-8111-111111111111/57d3ef22-2222-4222-8222-222222222222/file',
            'timestamp' => (new FrozenClock(self::NOW))->now()->getTimestamp(),
            'type' => 'authenticated',
            'upload_preset' => 'sources_private',
        ];

        self::assertSame('test_key', $params['api_key']);
        self::assertSame('raw', $params['resource_type']);
        self::assertSame($expectedParameters['public_id'], $params['public_id']);
        self::assertSame($expectedParameters['timestamp'], $params['timestamp']);
        self::assertSame(
            ApiUtils::signParameters($expectedParameters, 'test_secret'),
            $params['signature'],
        );
        self::assertSame([], $this->http->requests);
    }

    public function testCreatesExpiringApiSignedUrlsForPdfSnapshotAndJpegPageImages(): void
    {
        $service = $this->service();
        $source = 'sources/4afc2d11-1111-4111-8111-111111111111/57d3ef22-2222-4222-8222-222222222222/';

        $pdf = $service->signedUrl($source . 'file', 900);
        $snapshot = $service->signedUrl($source . 'snapshot', 600);
        $image = $service->signedUrl($source . 'p1', 600);

        self::assertStringContainsString('/raw/download', $pdf);
        self::assertStringContainsString('/raw/download', $snapshot);
        self::assertStringContainsString('/image/download', $image);
        parse_str((string) parse_url($pdf, PHP_URL_QUERY), $pdfParams);
        parse_str((string) parse_url($snapshot, PHP_URL_QUERY), $snapshotParams);
        parse_str((string) parse_url($image, PHP_URL_QUERY), $imageParams);

        self::assertSame('pdf', $pdfParams['format']);
        self::assertSame('txt', $snapshotParams['format']);
        self::assertSame('jpg', $imageParams['format']);
        self::assertSame((new FrozenClock(self::NOW))->now()->getTimestamp() + 900, (int) $pdfParams['expires_at']);
        self::assertSame('authenticated', $pdfParams['type']);
        self::assertArrayHasKey('signature', $imageParams);
        self::assertArrayNotHasKey('api_secret', $imageParams);
    }

    public function testRejectsUnsupportedTransformationsAndInvalidDownloadLifetimes(): void
    {
        $service = $this->service();
        $publicId = 'sources/4afc2d11-1111-4111-8111-111111111111/57d3ef22-2222-4222-8222-222222222222/p1';

        try {
            $service->signedUrl($publicId, 600, 'w_400');
            self::fail('API-signed downloads must not silently ignore transformations.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('do not support', $exception->getMessage());
        }

        foreach ([0, 3601] as $ttl) {
            try {
                $service->signedUrl($publicId, $ttl);
                self::fail('Out-of-range download lifetimes must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('TTL', $exception->getMessage());
            }
        }
    }

    public function testUploadsPrivateFilesWithAuthenticatedDeliveryAndReturnsStoredMetadata(): void
    {
        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'skimr-cloudinary-');
        self::assertIsString($this->temporaryFile);
        file_put_contents($this->temporaryFile, 'pdf bytes');
        $service = $this->service([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'public_id' => self::ROOT . '/sources/user/source/file.pdf',
                'bytes' => 9,
                'format' => 'pdf',
                'resource_type' => 'raw',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $uploaded = $service->uploadPrivate($this->temporaryFile, 'sources/user/source/file', 'raw');
        $request = $this->http->requests[0];

        self::assertSame(self::ROOT . '/sources/user/source/file.pdf', $uploaded['public_id']);
        self::assertSame(9, $uploaded['bytes']);
        self::assertSame('pdf', $uploaded['format']);
        self::assertSame('raw', $uploaded['resource_type']);
        self::assertStringContainsString('/raw/upload', (string) $request->getUri());
        $requestBody = (string) $request->getBody();
        self::assertStringContainsString('name="type"', $requestBody);
        self::assertStringContainsString('authenticated', $requestBody);
        self::assertStringContainsString('sources_private', $requestBody);
    }

    public function testUploadsAvatarsToThePublicPreset(): void
    {
        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'skimr-avatar-');
        self::assertIsString($this->temporaryFile);
        file_put_contents($this->temporaryFile, 'image bytes');
        $service = $this->service([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'public_id' => self::ROOT . '/avatars/4afc2d11-1111-4111-8111-111111111111',
                'bytes' => 11,
                'format' => 'jpg',
                'resource_type' => 'image',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $uploaded = $service->uploadAvatar(
            $this->temporaryFile,
            '4afc2d11-1111-4111-8111-111111111111',
        );
        $requestBody = (string) $this->http->requests[0]->getBody();

        self::assertSame('image', $uploaded['resource_type']);
        self::assertStringContainsString('/image/upload', (string) $this->http->requests[0]->getUri());
        self::assertStringContainsString('name="type"', $requestBody);
        self::assertStringContainsString('upload', $requestBody);
        self::assertStringContainsString('avatars', $requestBody);
    }

    public function testDeletesAuthenticatedSourceAndSurfacesNonSuccessResponses(): void
    {
        $service = $this->service([
            new Response(200, ['Content-Type' => 'application/json'], '{"result":"ok"}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"result":"not found"}'),
        ]);

        $service->delete('sources/user/source/p1');
        self::assertStringContainsString('/image/destroy', (string) $this->http->requests[0]->getUri());
        self::assertStringContainsString('authenticated', (string) $this->http->requests[0]->getBody());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cloudinary did not confirm asset deletion.');
        $service->delete('sources/user/source/file');
    }

    public function testRejectsInvalidUploadPathsFilesAndResourceTypes(): void
    {
        $service = $this->service();

        foreach ([
            static fn () => $service->signedUploadParams('../outside', 'raw'),
            static fn () => $service->signedUploadParams('sources/user/source/file', 'video'),
            static fn () => $service->uploadPrivate('missing-file.pdf', 'sources/user/source/file', 'raw'),
            static fn () => $service->signedUrl('sources/user/source/unknown', 600),
            static fn () => $service->delete('sources/user/source/unknown'),
        ] as $invalidCall) {
            try {
                $invalidCall();
                self::fail('Invalid Cloudinary inputs must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertSame([], $this->http->requests);
            }
        }
    }

    /** @param list<Response> $responses */
    private function service(array $responses = []): CloudinaryService
    {
        $this->http = new FakePsr18Client($responses);
        $cloudinary = new Cloudinary(self::CLOUDINARY_URL);
        $cloudinary->setHttpClient($this->http);

        return new CloudinaryService(
            $cloudinary,
            new FrozenClock(self::NOW),
            self::ROOT,
        );
    }
}
