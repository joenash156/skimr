<?php

declare(strict_types=1);

namespace App\Media;

use App\Shared\Clock\Clock;
use Cloudinary\Api\ApiUtils;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\CloudConfig;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

final class CloudinaryService
{
    private const MAX_DOWNLOAD_TTL = 3600;

    private readonly string $rootFolder;

    public function __construct(
        private readonly Cloudinary $cloudinary,
        private readonly Clock $clock,
        string $rootFolder,
    ) {
        $rootFolder = trim($rootFolder, '/');
        if ($rootFolder === '' || self::containsInvalidPath($rootFolder)) {
            throw new InvalidArgumentException('A valid Cloudinary root folder is required.');
        }

        $this->rootFolder = $rootFolder;
    }

    /**
     * Uploads a private PDF or page image as an authenticated Cloudinary asset.
     *
     * @return array{public_id: string, bytes: int, format: string, resource_type: string}
     */
    public function uploadPrivate(string $localPath, string $publicId, string $resourceType): array
    {
        $this->assertResourceType($resourceType);
        $this->assertReadableFile($localPath);
        $publicId = $this->assetPublicId($publicId);

        $response = $this->cloudinary->uploadApi()->upload($localPath, [
            'public_id' => $publicId,
            'type' => 'authenticated',
            'resource_type' => $resourceType,
            'upload_preset' => 'sources_private',
            'overwrite' => false,
            'unique_filename' => false,
            'use_filename' => false,
            'discard_original_filename' => true,
        ]);
        $uploaded = $response->getArrayCopy();
        $uploadedPublicId = $uploaded['public_id'] ?? null;
        $bytes = $uploaded['bytes'] ?? null;
        $format = $uploaded['format'] ?? null;
        $uploadedResourceType = $uploaded['resource_type'] ?? null;
        if (
            !is_string($uploadedPublicId)
            || !is_int($bytes)
            || !is_string($format)
            || !is_string($uploadedResourceType)
            || $uploadedResourceType !== $resourceType
        ) {
            throw new UnexpectedValueException('Cloudinary returned incomplete upload metadata.');
        }

        return [
            'public_id' => $uploadedPublicId,
            'bytes' => $bytes,
            'format' => $format,
            'resource_type' => $uploadedResourceType,
        ];
    }

    /**
     * Uploads an avatar using the configured public avatar preset.
     *
     * @return array{public_id: string, bytes: int, format: string, resource_type: string}
     */
    public function uploadAvatar(string $localPath, string $userUuid): array
    {
        $this->assertReadableFile($localPath);
        if (!\Ramsey\Uuid\Uuid::isValid($userUuid)) {
            throw new InvalidArgumentException('A valid user UUID is required.');
        }

        $publicId = $this->rootFolder . '/avatars/' . strtolower($userUuid);
        $response = $this->cloudinary->uploadApi()->upload($localPath, [
            'public_id' => $publicId,
            'type' => 'upload',
            'resource_type' => 'image',
            'upload_preset' => 'avatars',
            'overwrite' => true,
            'unique_filename' => false,
            'use_filename' => false,
            'discard_original_filename' => true,
        ]);
        $uploaded = $response->getArrayCopy();
        $uploadedPublicId = $uploaded['public_id'] ?? null;
        $bytes = $uploaded['bytes'] ?? null;
        $format = $uploaded['format'] ?? null;
        $resourceType = $uploaded['resource_type'] ?? null;
        if (!is_string($uploadedPublicId) || !is_int($bytes) || !is_string($format) || $resourceType !== 'image') {
            throw new UnexpectedValueException('Cloudinary returned incomplete avatar metadata.');
        }

        return [
            'public_id' => $uploadedPublicId,
            'bytes' => $bytes,
            'format' => $format,
            'resource_type' => $resourceType,
        ];
    }

    /**
     * Creates a short-lived API-signed download URL for a stored source asset.
     *
     * Page images are downloaded as JPEG because API-signed download URLs do
     * not support the optional delivery transformations.
     */
    public function signedUrl(string $publicId, int $ttlSeconds, mixed $transformation = null): string
    {
        if ($transformation !== null) {
            throw new InvalidArgumentException('API-signed download URLs do not support delivery transformations.');
        }
        if ($ttlSeconds < 1 || $ttlSeconds > self::MAX_DOWNLOAD_TTL) {
            throw new InvalidArgumentException('Cloudinary download URL TTL must be between 1 and 3600 seconds.');
        }

        $publicId = $this->assetPublicId($publicId);
        [$assetType, $format] = $this->downloadDetails($publicId);

        return $this->cloudinary->uploadApi()->privateDownloadUrl($publicId, $format, [
            'resource_type' => $assetType,
            'type' => 'authenticated',
            'expires_at' => $this->clock->now()->getTimestamp() + $ttlSeconds,
        ]);
    }

    /**
     * Creates parameters for Python to upload an authenticated asset directly.
     *
     * @return array{api_key: string, timestamp: int, signature: string, public_id: string, type: string, upload_preset: string, resource_type: string}
     */
    public function signedUploadParams(string $publicId, string $resourceType): array
    {
        $this->assertResourceType($resourceType);
        $publicId = $this->assetPublicId($publicId);
        $timestamp = $this->clock->now()->getTimestamp();
        $parameters = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
            'type' => 'authenticated',
            'upload_preset' => 'sources_private',
        ];
        $cloud = $this->cloudinary->configuration->cloud;
        if (!is_string($cloud->apiKey) || $cloud->apiKey === '' || !is_string($cloud->apiSecret) || $cloud->apiSecret === '') {
            throw new RuntimeException('Cloudinary API credentials are not configured.');
        }

        return [
            'api_key' => $cloud->apiKey,
            'timestamp' => $timestamp,
            'signature' => ApiUtils::signParameters(
                $parameters,
                $cloud->apiSecret,
                $cloud->signatureAlgorithm ?? CloudConfig::DEFAULT_SIGNATURE_ALGORITHM,
                $cloud->signatureVersion ?? CloudConfig::DEFAULT_SIGNATURE_VERSION,
            ),
            'public_id' => $publicId,
            'type' => 'authenticated',
            'upload_preset' => 'sources_private',
            'resource_type' => $resourceType,
        ];
    }

    /** Deletes an asset; succeeds if it was already gone. */
    public function delete(string $publicId): void
    {
        $publicId = $this->assetPublicId($publicId);
        $assetType = $this->downloadDetails($publicId)[0];
        $deliveryType = preg_match('~/avatars/[0-9a-f-]{36}$~i', $publicId) === 1 ? 'upload' : 'authenticated';

        $response = $this->cloudinary->uploadApi()->destroy($publicId, [
            'resource_type' => $assetType,
            'type' => $deliveryType,
            'invalidate' => true,
        ]);
        // "not found" means the asset is already gone (a retried purge, a double delete):
        // deleting is idempotent, so only a real failure is an error.
        if (!in_array($response['result'] ?? null, ['ok', 'not found'], true)) {
            throw new RuntimeException('Cloudinary did not confirm asset deletion.');
        }
    }

    private function assetPublicId(string $publicId): string
    {
        $publicId = trim($publicId, '/');
        if ($publicId === '' || self::containsInvalidPath($publicId)) {
            throw new InvalidArgumentException('A valid Cloudinary public ID is required.');
        }

        return str_starts_with($publicId, $this->rootFolder . '/')
            ? $publicId
            : $this->rootFolder . '/' . $publicId;
    }

    private function assertReadableFile(string $localPath): void
    {
        if (!is_file($localPath) || !is_readable($localPath)) {
            throw new InvalidArgumentException('The local upload file must exist and be readable.');
        }
    }

    private function assertResourceType(string $resourceType): void
    {
        if (!in_array($resourceType, ['image', 'raw'], true)) {
            throw new InvalidArgumentException('Cloudinary source uploads must use the image or raw resource type.');
        }
    }

    /**
     * @return array{string, string}
     */
    private function downloadDetails(string $publicId): array
    {
        if (preg_match('~/sources/[^/]+/[^/]+/file(?:\.pdf)?$~', $publicId) === 1) {
            return ['raw', 'pdf'];
        }
        if (preg_match('~/sources/[^/]+/[^/]+/snapshot(?:\.txt)?$~', $publicId) === 1) {
            return ['raw', 'txt'];
        }
        if (preg_match('~/sources/[^/]+/[^/]+/p(?:[1-9]|1[0-9]|20)$~', $publicId) === 1) {
            return ['image', 'jpg'];
        }
        if (preg_match('~/avatars/[0-9a-f-]{36}$~i', $publicId) === 1) {
            return ['image', 'jpg'];
        }

        throw new InvalidArgumentException('Cloudinary public ID does not match a supported source asset.');
    }

    private static function containsInvalidPath(string $path): bool
    {
        return preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\x00-\x20\\\\?#]~', $path) === 1
            || preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$~', $path) !== 1;
    }
}
