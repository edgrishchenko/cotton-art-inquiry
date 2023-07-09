<?php declare(strict_types=1);

namespace Pix\Inquiry\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FileUploader
{
    public function __construct(
        private readonly string $targetDirectory,
        private readonly string $projectDir,
        private readonly ValidatorInterface $validator,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function upload(array $files, SalesChannelContext $context): array
    {
        $definition = [];
        if ($this->getMaxFileSize($context)) {
            $definition['maxSize'] = $this->getMaxFileSize($context);
        }

        if ($this->getAllowedFileExtensions($context)) {
            $definition['extensions'] = $this->getAllowedFileExtensions($context);
        }

        $slugger = new AsciiSlugger();
        $fileFolder = $this->getTargetDirectory() . '/' . uniqid();
        $uploadedFiles = [];

        if (count($definition) > 0) {
            foreach ($files as $file) {
                $violations = $this->validator->validate($file, new File($definition));

                if ($violations->count() > 0) {
                    throw new FileException($violations[0]->getMessage());
                }
            }
        }
        unset($file);

        foreach ($files as $file) {
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $fileName = $safeFilename . '.' . $file->guessExtension();

            $file->move($this->projectDir . $fileFolder, $fileName);
            $uploadedFiles[] = $fileFolder . '/' . $fileName;
        }

        return $uploadedFiles;
    }

    private function getAllowedFileExtensions(SalesChannelContext $context): array
    {
        $allowedFileExtensions = $this->systemConfigService->get('PixInquiry.config.allowedFileExtensions', $context->getSalesChannelId());

        return array_map(fn($allowedFileExtension): string => substr(trim($allowedFileExtension), 1), explode(',', $allowedFileExtensions));
    }

    private function getMaxFileSize(SalesChannelContext $context): string
    {
        $maxFileSize = $this->systemConfigService->get('PixInquiry.config.maxFileSize', $context->getSalesChannelId());

        return $maxFileSize . 'M';
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}