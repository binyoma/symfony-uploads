<?php

namespace App\Service;

use Gedmo\Sluggable\Util\Urlizer;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploaderHelper
{
    const ARTICLE_IMAGE= 'article_image';
    const ARTICLE_REFERENCE= 'article_reference';
    private $filesystem ;

    private $privateFilesystem;

    private $requestStackContext;
    private $logger;

    private $publicAssetBaseUrl;

    public function __construct(FilesystemInterface $publicUploadFilesystem,FilesystemInterface $privateUploadFilesystem,RequestStackContext $requestStackContext, LoggerInterface $logger, string  $uploadedAssetsBaseUrl)
    {
        $this->filesystem = $publicUploadFilesystem;
        $this->privateFilesystem = $privateUploadFilesystem;
        $this->requestStackContext = $requestStackContext;
        $this->logger = $logger;
        $this->publicAssetBaseUrl = $uploadedAssetsBaseUrl;

    }

    public function uploadArticleImage(File $file, ?string $existingFilenamme):string
    {

        $newFilename =$this->uploadFile($file, self::ARTICLE_IMAGE, true);
        if ($existingFilenamme) {
            try {
               $result=  $this->filesystem->delete(self::ARTICLE_IMAGE.'/'.$existingFilenamme);
                if ($result === false) {
                    throw new \Exception(sprintf('Could not old file: %s', $existingFilenamme));
                }
            }catch (FileNotFoundException $e){
                 $this->logger->alert(sprintf('Old uploaded file "%s" was missing when trying to delete', $existingFilenamme));
            }

        }
        return $newFilename;

    }
    public function uploadArticleReference(File $file): string
    {
       return $this->uploadFile($file, self::ARTICLE_REFERENCE, false);

    }
    public function getPublicPath(string $path):string
    {
        return $this->requestStackContext
            ->getBasePath().$this->publicAssetBaseUrl .'/'.$path;

    }

    /**
     * @resource
     */
    public function readStream(string $path, bool $isPublic)
    {
        $filesystem = $isPublic? $this->filesystem : $this->privateFilesystem;
        $resource = $filesystem->readStream($path);
        if ($resource === false) {
            throw new \Exception(sprintf('Could not read stream: %s', $path));
        }
        return $resource;

    }

    private function uploadFile(File $file, string $directory, bool $isPublic):string
    {

        if ($file instanceof UploadedFile) {
            $originalFilename = $file->getClientOriginalName();
        }else{
            $originalFilename = $file->getFilename();
        }

        $newFilename =Urlizer::urlize(pathinfo($originalFilename, PATHINFO_FILENAME) ).'-'. uniqid().'.'. $file->guessExtension();

        $filesystem = $isPublic? $this->filesystem : $this->privateFilesystem;

        $stream = fopen($file->getPathname(), 'r');

        $result = $filesystem->writeStream(
            $directory.'/'.$newFilename,
            $stream
        );
        if ($result === false) {
            throw new \Exception(sprintf('Could not write new file: %s', $newFilename));
        }
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $newFilename;

    }

}